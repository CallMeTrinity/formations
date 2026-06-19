<?php

namespace App\Repository;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use App\Entity\User;
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

    /**
     * Durée par défaut d'un chapitre (minutes) quand sa formation n'a pas de
     * `estimatedMinutes` renseigné — typiquement le contenu issu de la sync, dont
     * le champ admin reste null. Évite que ces formations comptent pour zéro.
     */
    public const int DEFAULT_CHAPTER_MINUTES = 20;

    /**
     * Minutes de formation accomplies par l'utilisateur depuis `$since` (objectif
     * hebdomadaire). Chaque chapitre terminé compte pour une fraction du temps
     * estimé de sa formation (estimatedMinutes / nombre de chapitres) ; à défaut
     * d'estimation, pour {@see self::DEFAULT_CHAPTER_MINUTES}.
     */
    public function sumMinutesCompletedSince(User $user, \DateTimeImmutable $since): int
    {
        $rows = $this->createQueryBuilder('cp')
            ->select('f.estimatedMinutes AS estimatedMinutes')
            ->addSelect('COUNT(DISTINCT cp.id) AS completedThisWeek')
            ->addSelect('(SELECT COUNT(c2.id) FROM '.Chapter::class.' c2 WHERE c2.formation = f.id) AS totalChapters')
            ->join('cp.chapter', 'c')
            ->join('c.formation', 'f')
            ->join('cp.enrollment', 'e')
            ->where('e.user = :user')
            ->andWhere('cp.completedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('f.id')
            ->getQuery()
            ->getScalarResult();

        $minutes = 0.0;
        foreach ($rows as $row) {
            $total = (int) $row['totalChapters'];
            $perChapter = (null !== $row['estimatedMinutes'] && $total > 0)
                ? (int) $row['estimatedMinutes'] / $total
                : self::DEFAULT_CHAPTER_MINUTES;
            $minutes += $perChapter * (int) $row['completedThisWeek'];
        }

        return (int) round($minutes);
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
