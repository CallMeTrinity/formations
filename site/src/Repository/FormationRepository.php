<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * Formations visibles pour les listes, selon les droits de l'appelant.
     *
     * @return Formation[]
     */
    public function findVisible(bool $isAuthenticated, bool $isAdmin): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.visibility IN (:visibilities)')
            ->setParameter('visibilities', $this->visibilitiesFor($isAuthenticated, $isAdmin))
            ->orderBy('f.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les formations pour l'espace admin, sans filtre de visibilité
     * (l'admin voit aussi les brouillons), triées par titre.
     *
     * @return Formation[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catalogue filtrable : formations visibles pour l'appelant, éventuellement
     * restreintes à certains tags (slugs) et/ou certaines difficultés.
     *
     * Les filtres se combinent en ET (tags ET difficulté ET recherche) mais sont
     * en OU à l'intérieur d'un même critère (un des tags choisis, une des
     * difficultés, le terme dans le titre OU la description OU un tag).
     *
     * @param list<string>     $tagSlugs     slugs de tags à filtrer ; vide = pas de filtre tag
     * @param list<Difficulty> $difficulties difficultés à filtrer ; vide = pas de filtre niveau
     * @param string           $search       terme de recherche plein-texte ; vide = pas de recherche
     *
     * @return Formation[]
     */
    public function findCatalogue(bool $isAuthenticated, bool $isAdmin, array $tagSlugs = [], array $difficulties = [], string $search = ''): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.visibility IN (:visibilities)')
            ->setParameter('visibilities', $this->visibilitiesFor($isAuthenticated, $isAdmin))
            ->orderBy('f.title', 'ASC');

        if ([] !== $tagSlugs) {
            $qb->innerJoin('f.tags', 't')
                ->andWhere('t.slug IN (:tagSlugs)')
                ->setParameter('tagSlugs', $tagSlugs)
                // Une formation portant plusieurs des tags filtrés ne doit apparaître qu'une fois.
                ->distinct();
        }

        if ([] !== $difficulties) {
            $qb->andWhere('f.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $difficulties);
        }

        $search = trim($search);
        if ('' !== $search) {
            // Recherche sur titre, description et label de tag. Le leftJoin dédié
            // (alias « st ») évite de restreindre les résultats comme le ferait le
            // join du filtre tag ; distinct neutralise les doublons qu'il génère.
            $qb->leftJoin('f.tags', 'st')
                ->andWhere('LOWER(f.title) LIKE :search OR LOWER(f.description) LIKE :search OR LOWER(st.label) LIKE :search')
                ->setParameter('search', '%'.$this->escapeLike(mb_strtolower($search)).'%')
                ->distinct();
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Échappe les caractères joker LIKE (`%`, `_`) et l'antislash pour qu'un terme
     * saisi par l'utilisateur soit traité littéralement.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Visibilités autorisées pour l'appelant : public pour tous, beta pour les
     * connectés, brouillon pour les admins.
     *
     * @return list<Visibility>
     */
    public function visibilitiesFor(bool $isAuthenticated, bool $isAdmin): array
    {
        $visibilities = [Visibility::PUBLIC];

        if ($isAuthenticated) {
            $visibilities[] = Visibility::BETA;
        }
        if ($isAdmin) {
            $visibilities[] = Visibility::DRAFT;
        }

        return $visibilities;
    }

    /**
     * Candidates pour la recommandation : formations dont la visibilité est
     * autorisée et que l'utilisateur n'a pas encore terminées.
     *
     * @param list<Visibility> $visibilities visibilités autorisées pour l'appelant
     * @param list<int>        $excludedIds  identifiants à exclure (formations déjà terminées)
     *
     * @return Formation[]
     */
    public function findRecommendable(array $visibilities, array $excludedIds): array
    {
        if ([] === $visibilities) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.visibility IN (:visibilities)')
            ->setParameter('visibilities', $visibilities);

        if ([] !== $excludedIds) {
            $qb->andWhere('f.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $excludedIds);
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Formation[] Returns an array of Formation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Formation
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
