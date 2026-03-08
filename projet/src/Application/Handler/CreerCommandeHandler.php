<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreerCommandeCommand;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Domain\Repository\CommandeRepositoryInterface;
use App\Domain\ValueObject\Telephone;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handler pour créer une commande Mobile Money.
 */
final class CreerCommandeHandler
{
    public function __construct(
        private EvenementRepositoryInterface $evenementRepository,
        private CommandeRepositoryInterface $commandeRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.commission_taux%')]
        private float $commissionTaux,
        #[Autowire('%app.commande.expiration_minutes%')]
        private int $expirationMinutes
    ) {
    }

    public function handle(CreerCommandeCommand $command): Commande
    {
        // Validation du numéro
        $telephone = Telephone::fromString($command->numeroClient);
        
        $user = $this->userRepository->find($command->userId);
        if (!$user) {
            throw new \RuntimeException('Utilisateur non trouvé.');
        }

        $this->entityManager->beginTransaction();
        try {
            $total = 0.0;
            $lignesDonnees = [];

            // Verrouiller les événements
            foreach ($command->panier as $idEvenement => $quantite) {
                $evenement = $this->evenementRepository->findByIdWithLock($idEvenement);
                
                if (!$evenement) {
                    continue;
                }
                
                if (!$evenement->peutAccepterReservation($quantite)) {
                    throw new PlacesInsuffisantesException(
                        "Impossible de réserver {$quantite} places pour « {$evenement->getNom()} ». " .
                        "Places restantes : {$evenement->getPlacesRestantes()}"
                    );
                }
                
                $prix = $evenement->getPrixSimple();
                $sousTotal = $prix * $quantite;
                $total += $sousTotal;
                $lignesDonnees[] = [
                    'evenement' => $evenement,
                    'quantite' => $quantite,
                    'prix' => $prix
                ];
            }

            if ($total <= 0 || empty($lignesDonnees)) {
                throw new \RuntimeException('Panier invalide ou événements indisponibles.');
            }

            $commission = (float) round($total * $this->commissionTaux, 2);
            $montantNet = $total - $commission;

            // Créer la commande
            $commande = new Commande();
            $commande->setReference($this->genererReference());
            $commande->setMontantTotal($total);
            $commande->setNumeroClient($telephone->toString());
            $commande->setMethodePaiement($command->methodePaiement);
            $commande->setStatut('Pending');
            $commande->setCommissionPlateforme($commission);
            $commande->setMontantNetOrganisateur($montantNet);
            $commande->setClient($user);
            
            $dateExpiration = (new \DateTimeImmutable())->modify("+{$this->expirationMinutes} minutes");
            $commande->setDateExpiration($dateExpiration);

            // Créer les lignes de commande
            foreach ($lignesDonnees as $donnees) {
                $ligne = new CommandeLigne();
                $ligne->setCommande($commande);
                $ligne->setEvenement($donnees['evenement']);
                $ligne->setQuantite($donnees['quantite']);
                $ligne->setPrixUnitaire($donnees['prix']);
                $ligne->setTypeBillet('SIMPLE');
                $this->entityManager->persist($ligne);
            }

            $this->commandeRepository->save($commande);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $commande;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function genererReference(): string
    {
        do {
            $ref = 'EVT-' . random_int(1000, 9999) . '-' . strtoupper(bin2hex(random_bytes(2)));
        } while ($this->commandeRepository->referenceExists($ref));
        
        return $ref;
    }
}
