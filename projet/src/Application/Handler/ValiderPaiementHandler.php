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
use App\Service\Ticket\QrCodeGeneratorService;
use App\Service\Notification\BilletEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        private QrCodeGeneratorService $qrCodeGenerator,
        private BilletEmailService $billetEmailService,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {
    }

    public function handle(ValiderPaiementCommand $command): void
    {
        $commande = $this->commandeRepository->findByReference($command->referenceCommande);
        if (!$commande) {
            throw new \RuntimeException("Commande {$command->referenceCommande} introuvable.");
        }

        if (!$commande->getClient() && ($commande->getCheckoutEmail() ?? '') === '') {
            throw new \RuntimeException("Commande {$command->referenceCommande} sans email client.");
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
            $client = $commande->getClient();
            if (!$client) {
                $email = mb_strtolower(trim((string) $commande->getCheckoutEmail()));
                $client = $this->userRepository->findByEmail($email);
                if (!$client) {
                    $client = new User();
                    $client->setEmail($email);
                    $client->setNom('Compte à compléter');
                    $client->setTelephone(null);
                    $client->setRole('CLIENT');
                    $client->setActif(true);
                    $client->setIsVerified(false);
                    $client->setCheckoutAccount(true);
                    $client->setPassword($this->passwordHasher->hashPassword($client, bin2hex(random_bytes(24))));
                    $this->entityManager->persist($client);
                }
                $commande->setClient($client);
            }

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
                    $billet->setQrCode($this->qrCodeGenerator->generer());
                    $billet->setType($ligne->getTypeBillet());
                    $billet->setPrix($ligne->getPrixUnitaire());
                    $billet->setEvenement($evenementLocked);
                    $billet->setClient($client);
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

            // Envoyer l'email de confirmation
            try {
                $this->billetEmailService->envoyerConfirmationAchat($commande);
            } catch (\Throwable $e) {
                // Ne pas faire échouer la commande si l'email plante
                $this->logger->warning('Email confirmation non envoyé', [
                    'reference' => $commande->getReference(),
                    'error' => $e->getMessage(),
                ]);
            }
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
