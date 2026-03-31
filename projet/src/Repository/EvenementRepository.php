<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Retourne les événements actifs, triés par date.
     *
     * @param int|null $limite Nombre max de résultats (null = tous)
     * @return list<Evenement>
     */
    public function findActiveEvents(?int $limite = null): array
    {
        $ordre = ['dateEvenement' => 'ASC'];
        if ($limite !== null) {
            return $this->findBy(['isActive' => true], $ordre, $limite);
        }
        return $this->findBy(['isActive' => true], $ordre);
    }

    public function findUpcomingEvents(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('e')
            ->where('e.dateEvenement > :now')
            ->andWhere('e.isActive = :active')
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPastEvents(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('e')
            ->where('e.dateEvenement < :now')
            ->andWhere('e.isActive = :active')
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Événements passés dont l'organisateur n'a pas encore été payé.
     */
    public function findPastEventsNonPayes(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('e')
            ->where('e.dateEvenement < :now')
            ->andWhere('e.organisateurPaye = :false')
            ->andWhere('e.placesVendues > 0')
            ->setParameter('now', $now)
            ->setParameter('false', false)
            ->orderBy('e.dateEvenement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOrganisateur(User $organisateur): array
    {
        return $this->findBy(['organisateur' => $organisateur], ['createdAt' => 'DESC']);
    }

    /**
     * Pagination pour la liste des événements d'un organisateur (grosse quantité de données).
     */
    public function findPaginatedByOrganisateur(User $organisateur, int $page = 1, int $limit = 100, ?string $search = null): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $qb = $this->createQueryBuilder('e')
            ->where('e.organisateur = :organisateur')
            ->setParameter('organisateur', $organisateur)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->applyOrganisateurSearch($qb, $search);
        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre d'événements d'un organisateur (optionnellement filtré par recherche).
     */
    public function countByOrganisateur(User $organisateur, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.organisateur = :organisateur')
            ->setParameter('organisateur', $organisateur);
        $this->applyOrganisateurSearch($qb, $search);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyOrganisateurSearch($qb, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }
        $term = '%' . trim($search) . '%';
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->like('e.nom', ':search'),
                $qb->expr()->like('e.lieu', ':search'),
                $qb->expr()->like('e.ville', ':search'),
                $qb->expr()->like('e.description', ':search')
            )
        )->setParameter('search', $term);
    }

    public function searchEvents(string $query): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.nom LIKE :query OR e.description LIKE :query OR e.lieu LIKE :query OR e.ville LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche floue : propose des événements similaires basés sur des mots-clés individuels.
     * Utilisé quand la recherche exacte ne donne aucun résultat.
     */
    public function searchSimilarEvents(string $query, int $limit = 6): array
    {
        // Extraire les mots individuels (min 3 caractères)
        $words = array_filter(
            preg_split('/\s+/', mb_strtolower($query)),
            fn($word) => mb_strlen($word) >= 3
        );

        if (empty($words)) {
            return $this->findPopularEvents($limit);
        }

        $qb = $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->setParameter('active', true);

        // Construire une recherche OR pour chaque mot
        $orConditions = [];
        foreach ($words as $index => $word) {
            $paramName = 'word' . $index;
            $orConditions[] = $qb->expr()->orX(
                $qb->expr()->like('LOWER(e.nom)', ':' . $paramName),
                $qb->expr()->like('LOWER(e.description)', ':' . $paramName),
                $qb->expr()->like('LOWER(e.lieu)', ':' . $paramName),
                $qb->expr()->like('LOWER(e.ville)', ':' . $paramName),
                $qb->expr()->like('LOWER(e.categorie)', ':' . $paramName)
            );
            $qb->setParameter($paramName, '%' . $word . '%');
        }

        if (!empty($orConditions)) {
            $qb->andWhere($qb->expr()->orX(...$orConditions));
        }

        return $qb
            ->orderBy('e.placesVendues', 'DESC')
            ->addOrderBy('e.dateEvenement', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Extrait les termes/mots-clés uniques des événements actifs pour suggestions.
     */
    public function extractSearchKeywords(int $limit = 10): array
    {
        $events = $this->findActiveEvents(50);
        $keywords = [];

        foreach ($events as $event) {
            // Extraire mots du nom
            $words = preg_split('/\s+/', mb_strtolower($event->getNom()));
            foreach ($words as $word) {
                $word = trim($word, '.,;:!?');
                if (mb_strlen($word) >= 4 && !in_array($word, ['avec', 'pour', 'dans', 'sans', 'plus'])) {
                    $keywords[$word] = ($keywords[$word] ?? 0) + 1;
                }
            }

            // Ajouter catégorie
            if ($event->getCategorie()) {
                $cat = mb_strtolower($event->getCategorie());
                $keywords[$cat] = ($keywords[$cat] ?? 0) + 2;
            }

            // Ajouter ville
            if ($event->getVille()) {
                $ville = mb_strtolower($event->getVille());
                $keywords[$ville] = ($keywords[$ville] ?? 0) + 1;
            }
        }

        // Trier par fréquence décroissante
        arsort($keywords);
        return array_slice(array_keys($keywords), 0, $limit);
    }

    public function findPopularEvents(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.placesVendues > 0')
            ->setParameter('active', true)
            ->orderBy('e.placesVendues', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAvailableEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.placesDisponibles > e.placesVendues')
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveEvents(): int
    {
        return $this->count(['isActive' => true]);
    }

    public function save(Evenement $evenement, bool $flush = false): void
    {
        $this->getEntityManager()->persist($evenement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Evenement $evenement, bool $flush = false): void
    {
        $this->getEntityManager()->remove($evenement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Génère un slug unique à partir d'une base (ex. nom slugifié).
     * Normalise en [a-z0-9-] pour correspondre aux routes.
     * En édition, passer l'id de l'événement à exclure pour ne pas le considérer comme doublon.
     */
    public function generateUniqueSlug(string $baseSlug, ?int $excludeEventId = null): string
    {
        $slug = $this->normalizeSlugForRoute($baseSlug);
        if ($slug === '') {
            $slug = 'evenement';
        }

        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $existing = $this->findOneBy(['slug' => $slug]);
            if (!$existing) {
                return $slug;
            }
            if ($excludeEventId !== null && $existing->getId() === $excludeEventId) {
                return $slug;
            }
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }

    /**
     * Normalise une chaîne pour qu'elle respecte la contrainte de route [a-z0-9-]+.
     */
    private function normalizeSlugForRoute(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
        $s = preg_replace('/[\s-]+/', '-', $s);

        return trim($s, '-');
    }
}
