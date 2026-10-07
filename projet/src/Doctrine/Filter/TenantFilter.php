<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use App\Entity\Billet;
use App\Entity\CommandeLigne;
use App\Entity\Evenement;
use App\Entity\TicketDesign;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Cloisonne au niveau SQL les donnees appartenant a une organisation.
 *
 * L'isolation devient la valeur par defaut : une requete qui oublierait sa
 * clause « WHERE organisation = ... » reste cloisonnee. C'est l'inverse du
 * modele precedent, ou chaque requete devait y penser.
 *
 * La liste des entites est explicite, et non deduite de la presence d'une
 * colonne organisation_id : MembreOrganisation porte une telle colonne tout en
 * devant rester lisible en travers, sans quoi un membre de deux organisations
 * ne verrait plus qu'une seule de ses appartenances et ne pourrait plus
 * basculer.
 *
 * Le filtre n'est actif que lorsqu'un contexte de tenant est etabli (voir
 * ContexteTenant) : la navigation publique et l'administration voient tout.
 */
final class TenantFilter extends SQLFilter
{
    public const NOM = 'tenant';
    public const PARAMETRE = 'organisation_id';

    private const COLONNE = 'organisation_id';

    /**
     * Entites cloisonnees. Toute nouvelle entite portant des donnees de vendeur
     * doit etre ajoutee ici.
     *
     * @var list<class-string>
     */
    private const ENTITES_CLOISONNEES = [
        Evenement::class,
        Billet::class,
        CommandeLigne::class,
        TicketDesign::class,
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!\in_array($targetEntity->getName(), self::ENTITES_CLOISONNEES, true)) {
            return '';
        }

        try {
            $organisation = $this->getParameter(self::PARAMETRE);
        } catch (\InvalidArgumentException) {
            // Parametre non fourni : le filtre reste inerte plutot que bloquant.
            return '';
        }

        if ($organisation === '' || $organisation === null) {
            return '';
        }

        return sprintf('%s.%s = %s', $targetTableAlias, self::COLONNE, $organisation);
    }
}
