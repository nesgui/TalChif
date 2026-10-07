<?php

declare(strict_types=1);

namespace App\Service\Quota;

use App\Entity\Organisation;
use App\Entity\Plan;
use App\Repository\BilletRepository;
use App\Repository\EvenementRepository;
use App\Repository\MembreOrganisationRepository;
use App\Repository\PlanRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mesure la consommation d'une organisation et refuse au-dela de son plan.
 *
 * Les quotas sont bloquants : chaque methode « verifier... » leve une
 * QuotaDepasseException, appelee avant l'ecriture. Les methodes
 * « consommation... » servent l'affichage, sans effet de bord.
 *
 * Une limite a null est illimitee. Une organisation sans plan souscrit retombe
 * sur le plan par defaut (app.plan_par_defaut), et sur un plan illimite si ce
 * dernier n'existe pas en base : une plateforme mal initialisee ne doit pas
 * bloquer ses vendeurs.
 */
final class ServiceQuotas
{
    public const QUOTA_EVENEMENTS = 'evenements_actifs';
    public const QUOTA_BILLETS = 'billets_par_mois';
    public const QUOTA_MEMBRES = 'membres';

    public function __construct(
        private PlanRepository $planRepository,
        private EvenementRepository $evenementRepository,
        private BilletRepository $billetRepository,
        private MembreOrganisationRepository $membreRepository,
        #[Autowire(param: 'app.plan_par_defaut')]
        private string $codePlanParDefaut,
    ) {
    }

    public function planDe(Organisation $organisation): ?Plan
    {
        return $organisation->getPlan() ?? $this->planRepository->findParCode($this->codePlanParDefaut);
    }

    /**
     * @throws QuotaDepasseException
     */
    public function verifierCreationEvenement(Organisation $organisation): void
    {
        $limite = $this->planDe($organisation)?->getLimiteEvenementsActifs();
        if ($limite === null) {
            return;
        }

        $consomme = $this->evenementRepository->countActifsParOrganisation($organisation);
        if ($consomme >= $limite) {
            throw new QuotaDepasseException(
                self::QUOTA_EVENEMENTS,
                $consomme,
                $limite,
                sprintf(
                    'Votre offre autorise %d evenement(s) actif(s) et vous en avez %d. Desactivez un evenement ou changez d offre.',
                    $limite,
                    $consomme
                )
            );
        }
    }

    /**
     * @param int $quantite nombre de billets que l'operation va emettre
     *
     * @throws QuotaDepasseException
     */
    public function verifierEmissionBillets(Organisation $organisation, int $quantite): void
    {
        $limite = $this->planDe($organisation)?->getLimiteBilletsParMois();
        if ($limite === null) {
            return;
        }

        $consomme = $this->billetsDuMois($organisation);
        if ($consomme + $quantite > $limite) {
            throw new QuotaDepasseException(
                self::QUOTA_BILLETS,
                $consomme,
                $limite,
                sprintf(
                    'Votre offre autorise %d billet(s) par mois. %d deja emis, %d demandes.',
                    $limite,
                    $consomme,
                    $quantite
                )
            );
        }
    }

    /**
     * @throws QuotaDepasseException
     */
    public function verifierAjoutMembre(Organisation $organisation): void
    {
        $limite = $this->planDe($organisation)?->getLimiteMembres();
        if ($limite === null) {
            return;
        }

        $consomme = $this->membreRepository->compterParOrganisation($organisation);
        if ($consomme >= $limite) {
            throw new QuotaDepasseException(
                self::QUOTA_MEMBRES,
                $consomme,
                $limite,
                sprintf('Votre offre autorise %d membre(s) et l equipe en compte deja %d.', $limite, $consomme)
            );
        }
    }

    /**
     * Etat des quotas pour affichage.
     *
     * @return array<string, array{consomme: int, limite: int|null}>
     */
    public function consommation(Organisation $organisation): array
    {
        $plan = $this->planDe($organisation);

        return [
            self::QUOTA_EVENEMENTS => [
                'consomme' => $this->evenementRepository->countActifsParOrganisation($organisation),
                'limite' => $plan?->getLimiteEvenementsActifs(),
            ],
            self::QUOTA_BILLETS => [
                'consomme' => $this->billetsDuMois($organisation),
                'limite' => $plan?->getLimiteBilletsParMois(),
            ],
            self::QUOTA_MEMBRES => [
                'consomme' => $this->membreRepository->compterParOrganisation($organisation),
                'limite' => $plan?->getLimiteMembres(),
            ],
        ];
    }

    /**
     * Billets emis depuis le premier jour du mois calendaire courant.
     */
    private function billetsDuMois(Organisation $organisation): int
    {
        $debutDuMois = new \DateTimeImmutable('first day of this month midnight');

        return $this->billetRepository->countParOrganisationDepuis($organisation, $debutDuMois);
    }
}
