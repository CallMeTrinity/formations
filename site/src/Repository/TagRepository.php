<?php

namespace App\Repository;

use App\Entity\Tag;
use App\Enum\Visibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Tags proposés comme filtres du catalogue : uniquement ceux portés par au
     * moins une formation visible pour l'appelant (pas de filtre qui ne renvoie
     * rien), triés par libellé.
     *
     * @param list<Visibility> $visibilities visibilités autorisées pour l'appelant
     *
     * @return Tag[]
     */
    public function findForCatalogue(array $visibilities): array
    {
        if ([] === $visibilities) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->innerJoin('t.formations', 'f')
            ->andWhere('f.visibility IN (:visibilities)')
            ->setParameter('visibilities', $visibilities)
            ->groupBy('t.id')
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
