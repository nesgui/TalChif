<?php

declare(strict_types=1);

namespace App\Service\Achat;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\EvenementRepository;
use App\Service\Payment\PaymentInterface;
use App\Service\Ticket\TicketRenderService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Service métier pour le traitement des achats (paiement + création des billets).
 *
 * - Gère les transactions Doctrine pour garantir la cohérence (évite double réservation)
 * - Verrouillage pessimiste sur les événements pendant le paiement
 * - Délègue le paiement à PaymentInterface
 */
final class ServiceAchat
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private EntityManagerInterface $entityManager,
        private PaymentInterface $servicePaiement,
        private TicketRenderService $ticketRenderService
    ) {
    }

    /**
     * Traite un achat : vérification des places, paiement, création des billets.
     *
     * @param array<int, int> $panier [id_evenement => quantite]
     * @param string          $methodePaiement Une des constantes PaymentInterface::METHODE_*
     * @param string          $telephone Numéro pour Mobile Money
     * @return ResultatAchat Contient l'id de transaction et le message en cas de succès
     * @throws \RuntimeException En cas de panier invalide, places insuffisantes ou échec paiement
     */
    public function traiterAchat(array $panier, User $utilisateur, string $methodePaiement, string $telephone): ResultatAchat
    {
        if (!$this->servicePaiement->supports($methodePaiement)) {
            throw new \RuntimeException('Méthode de paiement non supportée.');
        }

        $this->entityManager->beginTransaction();
        try {
            // Verrouiller les événements pour éviter les race conditions
            $lignes = [];
            $total = 0.0;

            foreach ($panier as $idEvenement => $quantite) {
                $evenement = $this->evenementRepository->find($idEvenement, LockMode::PESSIMISTIC_WRITE);
                if (!$evenement || !$evenement->isActive()) {
                    continue;
                }
                if ($quantite > $evenement->getPlacesRestantes()) {
                    throw new \RuntimeException(
                        "Plus assez de places disponibles pour « {$evenement->getNom()} »."
                    );
                }
                $total += $evenement->getPrixSimple() * $quantite;
                $lignes[] = ['evenement' => $evenement, 'quantite' => $quantite];
            }

            if ($total <= 0 || empty($lignes)) {
                throw new \RuntimeException('Panier invalide ou événements indisponibles.');
            }

            $resultatPaiement = $this->servicePaiement->payer($total, $methodePaiement, [
                'telephone' => $telephone,
                'email' => $utilisateur->getUserIdentifier(),
            ]);

            if (!$resultatPaiement->isSuccess()) {
                throw new \RuntimeException('Paiement refusé : ' . $resultatPaiement->getMessage());
            }

            $transactionId = $resultatPaiement->getTransactionId();

            foreach ($lignes as $ligne) {
                /** @var Evenement $evenement */
                $evenement = $ligne['evenement'];
                $quantite = (int) $ligne['quantite'];

                for ($i = 0; $i < $quantite; $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->genererCodeQr());
                    $billet->setType('SIMPLE');
                    $billet->setPrix($evenement->getPrixSimple());
                    $billet->setEvenement($evenement);
                    $billet->setClient($utilisateur);
                    $billet->setOrganisateur($evenement->getOrganisateur());
                    $billet->setTransactionId($transactionId);
                    $billet->setStatutPaiement('PAYE');
                    $billet->validerPaiement();

                    $renderedPath = $this->ticketRenderService->renderAndStoreBilletPng($billet);
                    if ($renderedPath) {
                        $billet->setRenderedPngPath($renderedPath);
                    }
                    $this->entityManager->persist($billet);
                }

                $evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new ResultatAchat(
                $transactionId,
                $resultatPaiement->getMessage()
            );
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /** Génère un identifiant unique pour le QR code du billet. */
    private function genererCodeQr(): string
    {
        return 'BILLET_' . uniqid('', true) . '_' . time();
    }
}
