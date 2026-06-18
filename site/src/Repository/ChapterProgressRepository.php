<?php

namespace App\Repository;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChapterProgress>
 */
class ChapterProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChapterProgress::class);
    }

    public function findOneByEnrollmentAndChapter(Enrollment $enrollment, Chapter $chapter): ?ChapterProgress
    {
        return $this->findOneBy(['enrollment' => $enrollment, 'chapter' => $chapter]);
    }

    /**
     * Identifiants des chapitres terminés pour une inscription (pour pastiller le sommaire).
     *
     * @return list<int>
     */
    public function findCompletedChapterIds(Enrollment $enrollment): array
    {
        $rows = $this->createQueryBuilder('cp')
            ->select('IDENTITY(cp.chapter) AS chapterId')
            ->where('cp.enrollment = :enrollment')
            ->andWhere('cp.completedAt IS NOT NULL')
            ->setParameter('enrollment', $enrollment)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['chapterId'], $rows);
    }

    //    /**
    //     * @return ChapterProgress[] Returns an array of ChapterProgress objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ChapterProgress
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
