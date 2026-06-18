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
}
