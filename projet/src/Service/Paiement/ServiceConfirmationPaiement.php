<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Domain\Exception\PlacesInsuffisantesException;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\Evenement;
use App\Entity\LogSecurite;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\BilletEmailService;
use App\Service\Ticket\QrCodeGeneratorService;
use App\Service\Ticket\TicketRenderService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Proprietaire unique de l'invariant « paiement confirme ».
 *
 * Une confirmation emet les billets, decremente le stock de chaque evenement et
 * marque la commande payee. Ces trois effets sont indissociables : ils vivent
 * donc dans une seule transaction, ici et nulle part ailleurs.
 *
 * Les appelants ne gardent que leur specificite :
 *   - validation manuelle -> controle du montant et du numero expediteur
 *   - webhook PawaPay     -> machine a etats des statuts PawaPay
 *   - verification sondee -> interrogation de l'API PawaPay
 *
 * Idempotence : une commande deja payee renvoie un resultat « dejaConfirmee »
 * sans aucun effet de bord. Deux confirmations simultanees sont departagees par
 * le verrou pessimiste et par marquerPayee(), qui refuse un statut deja change.
 */
final class ServiceConfirmationPaiement
{
    public function __construct(
        private UserRepository $userRepository,
        private QrCodeGeneratorService $generateurQrCode,
        private TicketRenderService $renduBillet,
        private BilletEmailService $emailBillet,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hacheurMotDePasse,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
        #[Autowire('%app.cashback_taux%')]
        private float $tauxCashback,
    ) {
    }

    /**
     * @throws PlacesInsuffisantesException stock insuffisant au moment du verrou
     * @throws \RuntimeException            commande dans un etat non confirmable
     */
    public function confirmer(Commande $commande, ContexteConfirmation $contexte): ResultatConfirmation
    {
        $reference = $commande->getReference();

        $this->entityManager->beginTransaction();
        try {
            // Relecture dans la transaction : l'etat lu avant ouverture peut etre
            // perime, deux callbacks concurrents franchiraient sinon la meme garde.
            if ($this->entityManager->contains($commande)) {
                $this->entityManager->refresh($commande);
            }

            if ($commande->isPaid()) {
                $this->entityManager->rollback();
                $this->logger->info('Confirmation ignoree : commande deja payee', ['reference' => $reference]);

                return ResultatConfirmation::dejaConfirmee();
            }

            if (!$commande->peutEtreValidee()) {
                throw new \RuntimeException(
                    "La commande {$reference} n'est pas confirmable (statut : {$commande->getStatut()})."
                );
            }

            $client = $this->resoudreClient($commande);
            $nombreBillets = $this->emettreBillets($commande, $client);

            $commande->marquerPayee($this->resoudreValidateur($contexte));

            $cashback = $this->crediterCashback($commande, $client);

            $this->persisterJournal(
                $contexte,
                $contexte->actionJournal,
                $reference,
                sprintf('%s | Billets: %d | Cashback: %d', $contexte->detailsJournal, $nombreBillets, $cashback)
            );

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->journaliserEchec($contexte, $reference, $e);

            throw $e;
        }

        // Hors transaction : un email en echec ne doit pas annuler un paiement encaisse.
        $this->envoyerConfirmation($commande);

        return ResultatConfirmation::confirmee($nombreBillets, $cashback);
    }

    /**
     * Les commandes passees en paiement invite n'ont pas de client rattache :
     * il est retrouve, ou cree, a partir de l'email saisi au checkout.
     */
    private function resoudreClient(Commande $commande): User
    {
        $client = $commande->getClient();
        if ($client instanceof User) {
            return $client;
        }

        $email = mb_strtolower(trim((string) $commande->getCheckoutEmail()));
        if ($email === '') {
            throw new \RuntimeException(
                "La commande {$commande->getReference()} n'a ni client ni email de checkout."
            );
        }

        $client = $this->userRepository->findByEmail($email);
        if (!$client) {
            $client = new User();
            $client->setEmail($email);
            $client->setNom('Compte a completer');
            $client->setTelephone(null);
            $client->setRole('CLIENT');
            $client->setActif(true);
            $client->setIsVerified(false);
            $client->setCheckoutAccount(true);
            $client->setPassword($this->hacheurMotDePasse->hashPassword($client, bin2hex(random_bytes(24))));
            $this->entityManager->persist($client);
        }

        $commande->setClient($client);

        return $client;
    }

    /**
     * Emet les billets ligne par ligne et decremente le stock sous verrou.
     *
     * @return int nombre de billets emis
     */
    private function emettreBillets(Commande $commande, User $client): int
    {
        $total = 0;

        foreach ($commande->getLignes() as $ligne) {
            $evenement = $ligne->getEvenement();
            if (!$evenement) {
                throw new \RuntimeException('Ligne de commande sans evenement.');
            }

            // Verrou pessimiste : legitime uniquement parce qu'une transaction est ouverte.
            $evenementVerrouille = $this->entityManager->find(
                Evenement::class,
                $evenement->getId(),
                LockMode::PESSIMISTIC_WRITE
            );
            if (!$evenementVerrouille instanceof Evenement) {
                throw new \RuntimeException('Evenement introuvable.');
            }

            $quantite = $ligne->getQuantite();
            if ($quantite > $evenementVerrouille->getPlacesRestantes()) {
                throw new PlacesInsuffisantesException(
                    "Plus assez de places pour « {$evenementVerrouille->getNom()} »."
                );
            }

            for ($i = 0; $i < $quantite; $i++) {
                $billet = new Billet();
                $billet->setQrCode($this->generateurQrCode->generer());
                $billet->setType($ligne->getTypeBillet());
                $billet->setPrix($ligne->getPrixUnitaire());
                $billet->setEvenement($evenementVerrouille);
                $billet->setClient($client);
                $billet->setOrganisateur($evenementVerrouille->getOrganisateur());
                $billet->setTransactionId($commande->getReference());
                $billet->validerPaiement();

                $chemin = $this->rendreBillet($billet);
                if ($chemin !== null) {
                    $billet->setRenderedPngPath($chemin);
                }

                $this->entityManager->persist($billet);
                $total++;
            }

            $evenementVerrouille->reserverPlaces($quantite);
            $this->entityManager->persist($evenementVerrouille);
        }

        if ($total === 0) {
            throw new \RuntimeException("La commande {$commande->getReference()} ne contient aucune ligne.");
        }

        return $total;
    }

    /**
     * Le rendu PNG est un agrement : son echec ne doit pas bloquer l'emission.
     */
    private function rendreBillet(Billet $billet): ?string
    {
        try {
            return $this->renduBillet->renderAndStoreBilletPng($billet);
        } catch (\Throwable $e) {
            $this->logger->warning('Rendu PNG du billet impossible', [
                'qrCode' => $billet->getQrCode(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function crediterCashback(Commande $commande, User $client): int
    {
        $montant = (int) ($commande->getMontantTotal() * $this->tauxCashback);
        if ($montant <= 0) {
            return 0;
        }

        $client->addBalance($montant);
        $this->entityManager->persist($client);

        return $montant;
    }

    private function resoudreValidateur(ContexteConfirmation $contexte): ?User
    {
        if ($contexte->validateurId === null) {
            return null;
        }

        return $this->userRepository->find($contexte->validateurId);
    }

    /**
     * La trace d'echec est ecrite dans sa propre unite de travail : le rollback
     * vient d'annuler la precedente, un simple persist serait perdu.
     */
    private function journaliserEchec(ContexteConfirmation $contexte, string $reference, \Throwable $e): void
    {
        $this->logger->error('Confirmation de paiement en echec', [
            'reference' => $reference,
            'action' => $contexte->actionJournal,
            'error' => $e->getMessage(),
        ]);

        try {
            $this->persisterJournal(
                $contexte,
                $contexte->actionJournal . '_ECHEC',
                $reference,
                "Echec : {$e->getMessage()}"
            );
            $this->entityManager->flush();
        } catch (\Throwable $journalisation) {
            $this->logger->critical('Journal de securite non ecrit', [
                'reference' => $reference,
                'error' => $journalisation->getMessage(),
            ]);
        }
    }

    private function persisterJournal(ContexteConfirmation $contexte, string $action, string $reference, string $details): void
    {
        $log = new LogSecurite();
        $log->setAction($action);
        $log->setReferenceCommande($reference);
        $log->setDetails($details);
        $log->setIpAddress(
            $contexte->adresseIp
            ?? $this->requestStack->getCurrentRequest()?->getClientIp()
            ?? 'inconnue'
        );

        $this->entityManager->persist($log);
    }

    private function envoyerConfirmation(Commande $commande): void
    {
        try {
            $this->emailBillet->envoyerConfirmationAchat($commande);
        } catch (\Throwable $e) {
            $this->logger->warning('Email de confirmation non envoye', [
                'reference' => $commande->getReference(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
