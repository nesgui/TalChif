<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organisation>
 */
class OrganisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organisation::class);
    }

    public function findBySlug(string $slug): ?Organisation
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Genere un slug libre a partir d'un libelle.
     */
    public function genererSlugUnique(string $base): string
    {
        $racine = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($base)) ?? '', '-');
        $racine = $racine !== '' ? $racine : 'organisation';

        $slug = $racine;
        $suffixe = 1;
        while ($this->findBySlug($slug) !== null) {
            $suffixe++;
            $slug = $racine . '-' . $suffixe;
        }

        return $slug;
    }
}
