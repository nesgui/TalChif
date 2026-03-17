<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ValiderPaiementCommand;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Domain\Repository\CommandeRepositoryInterface;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Domain\Repository\BilletRepositoryInterface;
use App\Domain\ValueObject\Montant;
use App\Domain\ValueObject\Telephone;
use App\Entity\Billet;
use App\Entity\LogSecurite;
use App\Repository\LogSecuriteRepository;
use App\Repository\UserRepository;
use App\Service\Ticket\TicketRenderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handler pour valider un paiement Mobile Money.
 */
final class ValiderPaiementHandler
{
    public function __construct(
        private CommandeRepositoryInterface $commandeRepository,
        private EvenementRepositoryInterface $evenementRepository,
        private BilletRepositoryInterface $billetRepository,
        private UserRepository $userRepository,
        private LogSecuriteRepository $logSecuriteRepository,
        private TicketRenderService $ticketRenderService,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public function handle(ValiderPaiementCommand $command): void
    {
        $commande = $this->commandeRepository->findByReference($command->referenceCommande);
        if (!$commande) {
            throw new \RuntimeException("Commande {$command->referenceCommande} introuvable.");
        }

        if (!$commande->isPending() && !$commande->isProcessing()) {
            throw new \RuntimeException("La commande {$command->referenceCommande} n'est pas en attente.");
        }

        // Validation du montant
        $montantAttendu = Montant::fromFloat($commande->getMontantTotal());
        $montantRecu = Montant::fromFloat($command->montantRecu);

        if (!$montantRecu->estEgalA($montantAttendu)) {
            throw new \RuntimeException(
                "Montant incorrect. Attendu : {$montantAttendu}, Reçu : {$montantRecu}"
            );
        }

        // Validation du numéro
        $numeroClient = Telephone::fromString($command->numeroClient);
        $numeroCommande = Telephone::fromString($commande->getNumeroClient());

        if (!$numeroClient->equals($numeroCommande)) {
            throw new \RuntimeException(
                "Le numéro expéditeur ne correspond pas au numéro de la commande."
            );
        }

        // Vérifier tentatives de validation
        if ($commande->getTentativeValidation() >= 3) {
            throw new \RuntimeException(
                "Nombre maximum de tentatives de validation atteint pour cette commande."
            );
        }

        $commande->incrementerTentativeValidation();

        $this->entityManager->beginTransaction();
        try {
            // Générer les billets
            foreach ($commande->getLignes() as $ligne) {
                $evenement = $ligne->getEvenement();

                // Verrouiller l'événement
                $evenementLocked = $this->evenementRepository->findByIdWithLock($evenement->getId());
                if (!$evenementLocked) {
                    throw new \RuntimeException("Événement introuvable.");
                }

                $quantite = $ligne->getQuantite();
                if ($quantite > $evenementLocked->getPlacesRestantes()) {
                    throw new PlacesInsuffisantesException(
                        "Plus assez de places pour {$evenementLocked->getNom()}."
                    );
                }

                for ($i = 0; $i < $quantite; $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->genererCodeQr());
                    $billet->setType($ligne->getTypeBillet());
                    $billet->setPrix($ligne->getPrixUnitaire());
                    $billet->setEvenement($evenementLocked);
                    $billet->setClient($commande->getClient());
                    $billet->setOrganisateur($evenementLocked->getOrganisateur());
                    $billet->setTransactionId($commande->getReference());
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
                $evenementLocked->reserverPlaces($quantite);
                $this->evenementRepository->save($evenementLocked);
            }

            // Marquer la commande comme payée (logique métier dans l'entité)
            $validateur = $this->userRepository->find($command->validateurId);
            $commande->marquerPayee($validateur);
            $this->commandeRepository->save($commande);

            // Logger l'action
            $this->loggerAction(
                'VALIDATION_PAIEMENT',
                $commande->getReference(),
                "Paiement validé par admin ID {$command->validateurId}"
            );

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            // Logger l'échec
            $this->loggerAction(
                'VALIDATION_PAIEMENT_ECHEC',
                $commande->getReference(),
                "Échec validation : {$e->getMessage()}"
            );

            throw $e;
        }
    }

    private function genererCodeQr(): string
    {
        return 'BILLET_' . uniqid('', true) . '_' . time();
    }

    private function loggerAction(string $action, string $reference, string $details): void
    {
        $log = new LogSecurite();
        $log->setAction($action);
        $log->setReferenceCommande($reference);
        $log->setDetails($details);

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp() ?? 'unknown');
        }

        $this->logSecuriteRepository->save($log);
    }
}
