<?php

namespace App\Repository;

use App\Entity\Property;
use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Position libre suivante dans un logement. Sert à la reprise des
     * catégories historiques, qui ajoute des pièces à la suite de l'existant.
     */
    public function nextPositionFor(Property $property): int
    {
        $max = $this->createQueryBuilder('r')
            ->select('MAX(r.position)')
            ->andWhere('r.property = :property')
            ->setParameter('property', $property)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : ((int) $max) + 1;
    }
}
