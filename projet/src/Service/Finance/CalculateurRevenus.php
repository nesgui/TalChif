<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Evenement;
use App\Repository\BilletRepository;
use App\Service\CommissionRateProvider;

/**
 * Chiffres financiers d'un evenement.
 *
 * Le brut vient d'une requete, le net et la commission d'un calcul : seul le
 * premier releve du repository. Appliquer le taux de commission dans une
 * requete faisait dependre la couche d'acces aux donnees d'une regle metier.
 */
final class CalculateurRevenus
{
    public function __construct(
        private BilletRepository $billetRepository,
        private CommissionRateProvider $fournisseurTauxCommission,
    ) {
    }

    /**
     * Somme encaissee sur les billets payes, avant commission.
     */
    public function brut(Evenement $evenement): int
    {
        return $this->billetRepository->calculateGrossRevenue($evenement);
    }

    /**
     * Part retenue par la plateforme.
     */
    public function commission(Evenement $evenement): int
    {
        return (int) round($this->brut($evenement) * $this->fournisseurTauxCommission->getRate());
    }

    /**
     * Part reversee a l'organisateur.
     */
    public function net(Evenement $evenement): int
    {
        return $this->brut($evenement) - $this->commission($evenement);
    }
}
