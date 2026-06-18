<?php

namespace App\Repository;

use App\Entity\Formation;
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
        $visibilities = [Visibility::PUBLIC];

        if ($isAuthenticated) {
            $visibilities[] = Visibility::BETA;
        }
        if ($isAdmin) {
            $visibilities[] = Visibility::DRAFT;
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.visibility IN (:visibilities)')
            ->setParameter('visibilities', $visibilities)
            ->orderBy('f.title', 'ASC')
            ->getQuery()
            ->getResult();
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
