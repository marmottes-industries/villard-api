<?php

namespace App\Repository;

use App\Entity\Occupation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Occupation>
 */
class OccupationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Occupation::class);
    }

    /**
     * Occupations whose stay ends on the given day and that have not yet been
     * notified. Used by the daily end-of-stay notification command.
     *
     * @return Occupation[]
     */
    public function findEndingOn(\DateTimeImmutable $day): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.endDate = :day')
            ->andWhere('o.endNotifiedAt IS NULL')
            ->setParameter('day', $day->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Occupation[] Returns an array of Occupation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Occupation
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
