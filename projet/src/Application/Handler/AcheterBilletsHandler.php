<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\AcheterBilletsCommand;
use App\Domain\Exception\EvenementInactifException;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Domain\Repository\BilletRepositoryInterface;
use App\Domain\ValueObject\Telephone;
use App\Domain\ValueObject\Email;
use App\Entity\Billet;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Payment\PaymentInterface;
use App\Service\Payment\PaymentResult;
use App\Service\Ticket\TicketRenderService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handler pour la commande AcheterBilletsCommand.
 * Contient la logique métier d'achat de billets.
 */
final class AcheterBilletsHandler
{
    public function __construct(
        private EvenementRepositoryInterface $evenementRepository,
        private BilletRepositoryInterface $billetRepository,
        private UserRepository $userRepository,
        private PaymentInterface $paymentService,
        private TicketRenderService $ticketRenderService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function handle(AcheterBilletsCommand $command): ResultatAchat
    {
        // Validation du téléphone
        $telephone = Telephone::fromString($command->telephone);
        
        // Récupérer l'utilisateur
        $user = $this->userRepository->find($command->userId);
        if (!$user) {
            throw new \RuntimeException('Utilisateur non trouvé.');
        }

        if (!$this->paymentService->supports($command->methodePaiement)) {
            throw new \RuntimeException('Méthode de paiement non supportée.');
        }

        $this->entityManager->beginTransaction();
        try {
            $lignes = [];
            $total = 0.0;

            // Verrouiller les événements et calculer le total
            foreach ($command->panier as $idEvenement => $quantite) {
                $evenement = $this->evenementRepository->findByIdWithLock($idEvenement);
                
                if (!$evenement) {
                    continue;
                }
                
                if (!$evenement->isActive()) {
                    throw new EvenementInactifException(
                        "L'événement « {$evenement->getNom()} » n'est plus actif."
                    );
                }
                
                if ($quantite > $evenement->getPlacesRestantes()) {
                    throw new PlacesInsuffisantesException(
                        "Plus assez de places disponibles pour « {$evenement->getNom()} ». " .
                        "Seulement {$evenement->getPlacesRestantes()} places restantes."
                    );
                }
                
                $total += $evenement->getPrixSimple() * $quantite;
                $lignes[] = ['evenement' => $evenement, 'quantite' => $quantite];
            }

            if ($total <= 0 || empty($lignes)) {
                throw new \RuntimeException('Panier invalide ou événements indisponibles.');
            }

            // Effectuer le paiement
            $resultatPaiement = $this->paymentService->payer($total, $command->methodePaiement, [
                'telephone' => $telephone->toString(),
                'email' => $user->getUserIdentifier(),
            ]);

            if (!$resultatPaiement->isSuccess()) {
                throw new \RuntimeException('Paiement refusé : ' . $resultatPaiement->getMessage());
            }

            $transactionId = $resultatPaiement->getTransactionId();

            // Créer les billets
            foreach ($lignes as $ligne) {
                $evenement = $ligne['evenement'];
                $quantite = (int) $ligne['quantite'];

                for ($i = 0; $i < $quantite; $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->genererCodeQr());
                    $billet->setType('SIMPLE');
                    $billet->setPrix($evenement->getPrixSimple());
                    $billet->setEvenement($evenement);
                    $billet->setClient($user);
                    $billet->setOrganisateur($evenement->getOrganisateur());
                    $billet->setTransactionId($transactionId);
                    $billet->setStatutPaiement('PAYE');
                    $billet->validerPaiement();

                    // Générer le PNG du billet
                    $renderedPath = $this->ticketRenderService->renderAndStoreBilletPng($billet);
                    if ($renderedPath) {
                        $billet->setRenderedPngPath($renderedPath);
                    }

                    $this->billetRepository->save($billet);
                }

                // Réserver les places (logique métier dans l'entité)
                $evenement->reserverPlaces($quantite);
                $this->evenementRepository->save($evenement);
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

    private function genererCodeQr(): string
    {
        $secret = $_ENV['APP_SECRET'] ?? 'fallback-secret';
        $random = bin2hex(random_bytes(32));
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $random . $timestamp, $secret);
        return 'BILLET-' . strtoupper(substr($random, 0, 8)) . '-' . substr($signature, 0, 16);
    }
}

/**
 * DTO pour le résultat d'un achat.
 */
final readonly class ResultatAchat
{
    public function __construct(
        public string $transactionId,
        public string $message
    ) {
    }
}
