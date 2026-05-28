<?php

namespace App\Repository;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidature>
 */
class CandidatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidature::class);
    }

    /**
     * Returns an array of recruitment IDs the given user has already applied to.
     *
     * @return int[]
     */
    public function findAppliedRecruitmentIds(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.recruitment) AS rid')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'rid');
    }
}
