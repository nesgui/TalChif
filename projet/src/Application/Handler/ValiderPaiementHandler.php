<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ValiderPaiementCommand;
use App\Repository\CommandeRepository;
use App\Domain\ValueObject\Montant;
use App\Domain\ValueObject\Telephone;
use App\Service\Paiement\ContexteConfirmation;
use App\Service\Paiement\ResultatConfirmation;
use App\Service\Paiement\ServiceConfirmationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validation manuelle d'un paiement Mobile Money par un organisateur ou un admin.
 *
 * Specificite de ce chemin : le valideur saisit le montant recu et le numero
 * expediteur lus sur le SMS de l'operateur. Ce handler ne controle que ces deux
 * donnees, puis delegue l'emission des billets a ServiceConfirmationPaiement,
 * proprietaire unique de cette operation.
 */
final class ValiderPaiementHandler
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private ServiceConfirmationPaiement $serviceConfirmation,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.antifraude.tentatives_max')]
        private int $tentativesMax,
    ) {
    }

    public function handle(ValiderPaiementCommand $command): ResultatConfirmation
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

        if ($commande->getTentativeValidation() >= $this->tentativesMax) {
            throw new \RuntimeException(
                'Nombre maximum de tentatives de validation atteint pour cette commande.'
            );
        }

        // La tentative est enregistree AVANT les controles, et dans sa propre unite
        // de travail : un compteur antifraude n'a de sens que s'il retient les
        // echecs. Le rollback de la confirmation ne doit donc pas l'effacer.
        $commande->incrementerTentativeValidation();
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        $montantAttendu = Montant::fromFloat($commande->getMontantTotal());
        $montantRecu = Montant::fromFloat($command->montantRecu);
        if (!$montantRecu->estEgalA($montantAttendu)) {
            throw new \RuntimeException(
                "Montant incorrect. Attendu : {$montantAttendu}, Recu : {$montantRecu}"
            );
        }

        $numeroClient = Telephone::fromString($command->numeroClient);
        $numeroCommande = Telephone::fromString($commande->getNumeroClient());
        if (!$numeroClient->equals($numeroCommande)) {
            throw new \RuntimeException(
                'Le numero expediteur ne correspond pas au numero de la commande.'
            );
        }

        return $this->serviceConfirmation->confirmer(
            $commande,
            ContexteConfirmation::validationManuelle($command->validateurId)
        );
    }
}
