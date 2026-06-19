<?php

namespace App\Repository;

use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    /**
     * Inscription d'un utilisateur à une formation (unique par couple user/formation).
     */
    public function findOneByUserAndFormation(User $user, Formation $formation): ?Enrollment
    {
        return $this->findOneBy(['user' => $user, 'formation' => $formation]);
    }

    /**
     * Inscriptions d'un utilisateur, accompagnées de quoi calculer l'avancement :
     * nombre total de chapitres et nombre de chapitres terminés. Les deux jointures
     * (chapitres / progression) produisent un produit cartésien, neutralisé par les
     * COUNT(DISTINCT …). Trié par activité la plus récente d'abord.
     *
     * @return list<array{enrollment: Enrollment, chaptersCount: int, completedCount: int}>
     */
    public function findWithProgressForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e AS enrollment')
            ->addSelect('COUNT(DISTINCT c.id) AS chaptersCount')
            ->addSelect('COUNT(DISTINCT cp.id) AS completedCount')
            ->join('e.formation', 'f')
            ->leftJoin('f.chapters', 'c')
            ->leftJoin('e.chapterProgress', 'cp', 'WITH', 'cp.completedAt IS NOT NULL')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->groupBy('e.id')
            ->orderBy('e.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): array => [
            'enrollment' => $row['enrollment'],
            'chaptersCount' => (int) $row['chaptersCount'],
            'completedCount' => (int) $row['completedCount'],
        ], $rows);
    }

    /**
     * Identifiants des formations dans lesquelles l'utilisateur est inscrit.
     *
     * @return list<int>
     */
    public function findEnrolledUserIds(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.formation) AS formationId')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['formationId'], $rows);
    }

    /**
     * Nombre d'inscriptions par formation : signal de popularité pour le scoring.
     *
     * @return array<int, int> formationId => nombre d'inscrits
     */
    public function countByFormation(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.formation) AS formationId', 'COUNT(e.id) AS total')
            ->groupBy('e.formation')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['formationId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Inscrits et complétions par formation, pour les stats admin. Le nombre de
     * complétions compte les inscriptions actuellement terminées (completedAt non
     * nul, donc remis à zéro après un « recommencer »).
     *
     * @return array<int, array{enrolled: int, completed: int}>
     */
    public function statsByFormation(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.formation) AS formationId')
            ->addSelect('COUNT(e.id) AS enrolled')
            ->addSelect('SUM(CASE WHEN e.firstCompletedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed')
            ->groupBy('e.formation')
            ->getQuery()
            ->getScalarResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['formationId']] = [
                'enrolled' => (int) $row['enrolled'],
                'completed' => (int) $row['completed'],
            ];
        }

        return $stats;
    }
}
