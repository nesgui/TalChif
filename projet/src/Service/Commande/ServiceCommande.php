<?php

declare(strict_types=1);

namespace App\Service\Commande;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\LogSecurite;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\LogSecuriteRepository;
use App\Service\Ticket\TicketRenderService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service métier : création commande, expiration, validation paiement, activation billets.
 */
final class ServiceCommande
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommandeRepository $commandeRepository,
        private EvenementRepository $evenementRepository,
        private LogSecuriteRepository $logSecuriteRepository,
        private RequestStack $requestStack,
        private TicketRenderService $ticketRenderService,
        #[Autowire('%app.commission_taux%')]
        private float $commissionTaux,
        #[Autowire('%app.commande.expiration_minutes%')]
        private int $expirationMinutes,
        #[Autowire('%app.antifraude.tentatives_max%')]
        private int $tentativesMax,
        #[Autowire('%app.momo.numero%')]
        private string $momoNumero,
        #[Autowire('%app.momo.beneficiaire%')]
        private string $momoBeneficiaire
    ) {
    }

    /**
     * Génère une référence unique (ex: EVT-7842-X9K2).
     */
    public function genererReference(): string
    {
        do {
            $ref = 'EVT-' . random_int(1000, 9999) . '-' . strtoupper(bin2hex(random_bytes(2)));
        } while ($this->commandeRepository->referenceExiste($ref));
        return $ref;
    }

    /**
     * Crée une commande en statut Pending Payment.
     * Le numéro client est validé (format Tchad 235 XX XX XX XX) avant création.
     *
     * @param array<int, int> $panier [id_evenement => quantite]
     */
    public function creerCommande(array $panier, User $client, string $methodePaiement, string $numeroClient): Commande
    {
        $numeroClient = trim($numeroClient);
        if (!$this->numeroClientValide($numeroClient)) {
            throw new \RuntimeException('Format du numéro de téléphone invalide. Utilisez un numéro tchadien : 235 XX XX XX XX (8 chiffres après 235).');
        }

        $this->entityManager->beginTransaction();
        try {
            $total = 0.0;
            $lignesDonnees = [];

            foreach ($panier as $idEvenement => $quantite) {
                $evenement = $this->evenementRepository->find($idEvenement, LockMode::PESSIMISTIC_WRITE);
                if (!$evenement || !$evenement->isActive()) {
                    continue;
                }
                if ($quantite > $evenement->getPlacesRestantes()) {
                    throw new \RuntimeException("Plus assez de places pour « {$evenement->getNom()} ».");
                }
                $prix = $evenement->getPrixSimple();
                $sousTotal = $prix * $quantite;
                $total += $sousTotal;
                $lignesDonnees[] = ['evenement' => $evenement, 'quantite' => $quantite, 'prix' => $prix];
            }

            if ($total <= 0 || empty($lignesDonnees)) {
                throw new \RuntimeException('Panier invalide ou événements indisponibles.');
            }

            $commission = (float) round($total * $this->commissionTaux, 2);
            $montantNet = $total - $commission;

            $commande = new Commande();
            $commande->setReference($this->genererReference());
            $commande->setMontantTotal($total);
            $commande->setNumeroClient($numeroClient);
            $commande->setClient($client);
            $commande->setStatut(Commande::STATUT_PENDING);
            $commande->setDateExpiration((new \DateTimeImmutable())->modify("+{$this->expirationMinutes} minutes"));
            $commande->setMethodePaiement($methodePaiement);
            $commande->setCommissionPlateforme($commission);
            $commande->setMontantNetOrganisateur($montantNet);

            foreach ($lignesDonnees as $l) {
                $ligne = new CommandeLigne();
                $ligne->setEvenement($l['evenement']);
                $ligne->setQuantite($l['quantite']);
                $ligne->setPrixUnitaire($l['prix']);
                $ligne->setTypeBillet('SIMPLE');
                $commande->addLigne($ligne);
            }

            $this->entityManager->persist($commande);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $commande;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function getMomoNumero(): string
    {
        return $this->momoNumero;
    }

    public function getMomoBeneficiaire(): string
    {
        return $this->momoBeneficiaire;
    }

    /**
     * Expire les commandes dépassant la date limite.
     */
    public function expirerCommandes(): int
    {
        $commandes = $this->commandeRepository->findToExpire();
        foreach ($commandes as $c) {
            $c->setStatut(Commande::STATUT_EXPIRED);
            $this->entityManager->persist($c);
        }
        $this->entityManager->flush();
        return count($commandes);
    }

    /**
     * Valide un paiement manuellement (admin).
     * Vérifications antifraude : référence unique, montant exact, numéro, blocage tentatives.
     */
    public function validerPaiement(
        string $reference,
        float $montantRecu,
        string $numeroExpediteur,
        User $admin
    ): Commande {
        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande) {
            $this->log('VALIDATION_REF_INVALID', $reference, "Référence inexistante");
            throw new \RuntimeException('Référence inconnue.');
        }

        if (!$commande->isPending()) {
            $this->log('VALIDATION_STATUT_INVALID', $reference, "Statut actuel: {$commande->getStatut()}");
            throw new \RuntimeException('Cette commande n\'est plus en attente de paiement.');
        }

        if ($commande->estExpiree()) {
            $commande->setStatut(Commande::STATUT_EXPIRED);
            $this->entityManager->flush();
            $this->log('VALIDATION_EXPIRED', $reference, "Commande expirée");
            throw new \RuntimeException('Cette commande a expiré.');
        }

        if ($commande->getTentativeValidation() >= $this->tentativesMax) {
            $this->log('VALIDATION_BLOQUE', $reference, "Trop de tentatives: {$commande->getTentativeValidation()}");
            throw new \RuntimeException('Commande bloquée (trop de tentatives de validation).');
        }

        $commande->incrementerTentativeValidation();

        $numeroClientNormalise = $this->normaliserNumero($commande->getNumeroClient());
        $numeroExpNormalise = $this->normaliserNumero($numeroExpediteur);
        if ($numeroClientNormalise !== $numeroExpNormalise) {
            $this->entityManager->flush();
            $this->log('VALIDATION_NUMERO_MISMATCH', $reference, "Attendu: {$commande->getNumeroClient()}, Reçu: {$numeroExpediteur}");
            throw new \RuntimeException('Le numéro expéditeur ne correspond pas au numéro client de la commande.');
        }

        $ecart = abs($montantRecu - $commande->getMontantTotal());
        if ($ecart > 0.01) {
            $this->entityManager->flush();
            $this->log('VALIDATION_MONTANT_MISMATCH', $reference, "Attendu: {$commande->getMontantTotal()}, Reçu: {$montantRecu}");
            throw new \RuntimeException('Le montant reçu ne correspond pas exactement au montant de la commande.');
        }

        $this->entityManager->beginTransaction();
        try {
            $commande->setStatut(Commande::STATUT_PAID);
            $commande->setValidePar($admin);
            $commande->setDateValidation(new \DateTimeImmutable());

            foreach ($commande->getLignes() as $ligne) {
                $evenement = $ligne->getEvenement();
                $evenement = $this->evenementRepository->find($evenement->getId(), LockMode::PESSIMISTIC_WRITE);
                for ($i = 0; $i < $ligne->getQuantite(); $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->genererCodeQr());
                    $billet->setType($ligne->getTypeBillet());
                    $billet->setPrix($ligne->getPrixUnitaire());
                    $billet->setEvenement($evenement);
                    $billet->setClient($commande->getClient());
                    $billet->setOrganisateur($evenement->getOrganisateur());
                    $billet->setTransactionId($commande->getReference());
                    $billet->validerPaiement();

                    $renderedPath = $this->ticketRenderService->renderAndStoreBilletPng($billet);
                    if ($renderedPath) {
                        $billet->setRenderedPngPath($renderedPath);
                    }
                    $this->entityManager->persist($billet);
                }
                $evenement->setPlacesVendues($evenement->getPlacesVendues() + $ligne->getQuantite());
            }

            $this->log('VALIDATION_OK', $reference, "Paiement validé par {$admin->getEmail()}", $admin);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $commande;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function rejeterPaiement(string $reference, User $admin, string $raison = ''): void
    {
        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande) {
            throw new \RuntimeException('Référence inconnue.');
        }
        if (!$commande->isPending()) {
            throw new \RuntimeException('Commande non modifiable.');
        }
        $commande->setStatut(Commande::STATUT_REJECTED);
        $this->log('VALIDATION_REJECT', $reference, $raison ?: 'Rejetée par admin', $admin);
        $this->entityManager->flush();
    }

    private function genererCodeQr(): string
    {
        $secret = $_ENV['APP_SECRET'] ?? 'fallback-secret';
        $random = bin2hex(random_bytes(32));
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $random . $timestamp, $secret);
        return 'BILLET-' . strtoupper(substr($random, 0, 8)) . '-' . substr($signature, 0, 16);
    }

    private function normaliserNumero(string $numero): string
    {
        return preg_replace('/\D/', '', $numero);
    }

    /**
     * Vérifie que le numéro est un format Tchad valide (235 + 8 chiffres).
     */
    private function numeroClientValide(string $numero): bool
    {
        $cleaned = $this->normaliserNumero($numero);
        return preg_match('/^235\d{8}$/', $cleaned) === 1;
    }

    private function log(string $action, ?string $ref, ?string $details, ?User $user = null): void
    {
        $log = new LogSecurite();
        $log->setAction($action);
        $log->setReferenceCommande($ref);
        $log->setDetails($details);
        $log->setUtilisateur($user);
        $log->setCreatedAt(new \DateTimeImmutable());
        $req = $this->requestStack->getCurrentRequest();
        $log->setIpAddress($req?->getClientIp());
        $this->entityManager->persist($log);
    }
}
