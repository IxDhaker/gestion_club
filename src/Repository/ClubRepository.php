<?php

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /**
     * Find clubs where the user is either the president OR a member with the role 'Responsable'
     */
    public function findManagedClubs(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('App\Entity\ClubMember', 'cm', 'WITH', 'cm.club = c AND cm.user = :user AND cm.role = :roleResp')
            ->where('c.president = :user')
            ->orWhere('cm.id IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('roleResp', 'Responsable')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a user is the president or a 'Responsable' of the specific club
     */
    public function isManager(\App\Entity\Club $club, \App\Entity\User $user): bool
    {
        if ($club->getPresident() === $user) {
            return true;
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $count = $qb->select('count(cm.id)')
            ->from('App\Entity\ClubMember', 'cm')
            ->where('cm.club = :club')
            ->andWhere('cm.user = :user')
            ->andWhere('cm.role = :roleResp')
            ->setParameter('club', $club)
            ->setParameter('user', $user)
            ->setParameter('roleResp', 'Responsable')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
