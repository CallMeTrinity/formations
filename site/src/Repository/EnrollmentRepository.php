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
}
