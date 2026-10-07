<?php

declare(strict_types=1);

namespace App\Service\Tenant;

use App\Doctrine\Filter\TenantFilter;
use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\MembreOrganisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Organisation sous laquelle la requete courante travaille.
 *
 * Etablir le contexte active le filtre Doctrine : a partir de ce point, toute
 * lecture d'evenement, de billet, de ligne de commande ou de design de billet
 * est cloisonnee, sans que les requetes aient a le demander.
 *
 * Hors contexte (navigation publique, administration), le filtre reste inactif
 * et les donnees de toutes les organisations sont visibles.
 *
 * Le choix de l'organisation est memorise en session : un compte rattache a
 * plusieurs organisations bascule explicitement de l'une a l'autre, et le choix
 * est toujours revalide contre ses appartenances reelles.
 */
final class ContexteTenant
{
    public const CLE_SESSION = 'organisation_courante';

    /** Parametre de session PostgreSQL lu par les politiques RLS. */
    public const PARAMETRE_BASE = 'talchif.organisation_id';

    private ?Organisation $organisation = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembreOrganisationRepository $membreRepository,
        private RequestStack $requestStack,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function etablir(Organisation $organisation): void
    {
        $this->organisation = $organisation;

        $filtres = $this->entityManager->getFilters();
        if (!$filtres->isEnabled(TenantFilter::NOM)) {
            $filtres->enable(TenantFilter::NOM);
        }

        $filtres->getFilter(TenantFilter::NOM)
            ->setParameter(TenantFilter::PARAMETRE, (string) $organisation->getId(), 'integer');

        $this->poserParametreBase((string) $organisation->getId());
    }

    public function liberer(): void
    {
        $this->organisation = null;

        $filtres = $this->entityManager->getFilters();
        if ($filtres->isEnabled(TenantFilter::NOM)) {
            $filtres->disable(TenantFilter::NOM);
        }

        // Remise a zero obligatoire : la connexion est reutilisee d'une requete
        // a l'autre, et sous un worker elle vit tres longtemps.
        $this->poserParametreBase('');
    }

    public function organisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function estEtabli(): bool
    {
        return $this->organisation !== null;
    }

    /**
     * Organisation courante, ou echec : a utiliser quand le code ne peut pas
     * fonctionner sans tenant.
     */
    public function organisationRequise(): Organisation
    {
        if ($this->organisation === null) {
            throw new \LogicException('Aucune organisation n est etablie pour cette requete.');
        }

        return $this->organisation;
    }

    /**
     * Appartenances de l'utilisateur, organisations actives uniquement.
     *
     * @return MembreOrganisation[]
     */
    public function appartenances(User $utilisateur): array
    {
        return $this->membreRepository->findParUtilisateur($utilisateur);
    }

    /**
     * Resout l'organisation sous laquelle l'utilisateur travaille.
     *
     * Priorite au choix memorise en session, a condition qu'il corresponde
     * toujours a une appartenance active : un retrait d'equipe ne doit pas
     * laisser un acces ouvert par une session restee en place.
     */
    public function resoudrePour(User $utilisateur): ?Organisation
    {
        $appartenances = $this->appartenances($utilisateur);
        if ($appartenances === []) {
            return null;
        }

        $choisie = $this->identifiantMemorise();
        if ($choisie !== null) {
            foreach ($appartenances as $appartenance) {
                if ($appartenance->getOrganisation()?->getId() === $choisie) {
                    return $appartenance->getOrganisation();
                }
            }

            // Choix devenu invalide : on l'oublie plutot que de le subir.
            $this->oublierChoix();
        }

        return $appartenances[0]->getOrganisation();
    }

    /**
     * Memorise l'organisation choisie, apres verification de l'appartenance.
     *
     * @throws \RuntimeException l'utilisateur n'est pas membre de cette organisation
     */
    public function basculerVers(User $utilisateur, Organisation $organisation): void
    {
        $membre = $this->membreRepository->findAppartenance($utilisateur, $organisation);
        if ($membre === null || !$organisation->isActif()) {
            throw new \RuntimeException('Vous n etes pas membre de cette organisation.');
        }

        $this->session()?->set(self::CLE_SESSION, $organisation->getId());
        $this->etablir($organisation);
    }

    public function oublierChoix(): void
    {
        $this->session()?->remove(self::CLE_SESSION);
    }

    /**
     * Transmet l'organisation aux politiques Row Level Security.
     *
     * Le filtre Doctrine protege l'ORM, ce parametre protege la table : un SQL
     * brut reste cloisonne. Chaine vide : les politiques laissent tout passer,
     * ce qui preserve le catalogue public et l'administration.
     */
    private function poserParametreBase(string $valeur): void
    {
        try {
            $this->entityManager->getConnection()->executeStatement(
                'SELECT set_config(?, ?, false)',
                [self::PARAMETRE_BASE, $valeur]
            );
        } catch (\Throwable $e) {
            // Une base sans les politiques (hors PostgreSQL, migrations non
            // jouees) ne doit pas empecher l'application de fonctionner : le
            // filtre Doctrine assure deja le cloisonnement.
            $this->logger?->warning('Parametre de cloisonnement non transmis a la base', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function identifiantMemorise(): ?int
    {
        $valeur = $this->session()?->get(self::CLE_SESSION);

        return is_numeric($valeur) ? (int) $valeur : null;
    }

    private function session(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (\Throwable) {
            // Hors contexte HTTP (console, worker) : aucun choix memorise.
            return null;
        }
    }
}
