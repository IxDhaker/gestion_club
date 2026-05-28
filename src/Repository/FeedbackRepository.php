<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Feedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    /**
     * Returns feedbacks whose event belongs to one of the given clubs.
     *
     * @param Club[] $clubs
     * @return Feedback[]
     */
    public function findByClubs(array $clubs): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.event', 'e')
            ->andWhere('e.club IN (:clubs)')
            ->setParameter('clubs', $clubs)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
