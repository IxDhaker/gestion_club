<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * @return Participation[]
     */
    public function findReceivedByManager(User $manager): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.event', 'e')
            ->join('e.club', 'c')
            ->leftJoin('App\Entity\ClubMember', 'cm', 'WITH', 'cm.club = c AND cm.user = :manager AND cm.role = :roleResp')
            ->addSelect('e', 'c', 'u')
            ->leftJoin('p.user', 'u')
            ->where('c.president = :manager')
            ->orWhere('cm.id IS NOT NULL')
            ->setParameter('manager', $manager)
            ->setParameter('roleResp', 'Responsable')
            ->orderBy('p.dateParticipation', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<string> $statuses
     */
    public function countByEventAndStatuses(Event $event, array $statuses): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.event = :event')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('event', $event)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    //    /**
    //     * @return Participation[] Returns an array of Participation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Participation
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
