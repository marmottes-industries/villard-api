<?php

namespace App\Repository;

use App\Entity\Property;
use App\Entity\PropertyMember;
use App\Entity\User;
use App\Enum\PropertyRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyMember>
 */
class PropertyMemberRepository extends ServiceEntityRepository
{
    /**
     * Mémoïsation par requête HTTP : l'extension Doctrine interroge les
     * appartenances sur *chaque* requête de collection et d'item, le voter sur
     * chaque écriture. Le conteneur étant reconstruit à chaque requête, ce
     * cache ne survit jamais à la requête courante.
     *
     * @var array<int, list<int>>
     */
    private array $propertyIdsCache = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyMember::class);
    }

    /**
     * Identifiants des logements dont l'utilisateur est membre.
     *
     * @return list<int>
     */
    public function findPropertyIdsForUser(User $user): array
    {
        $userId = $user->getId();

        if (null === $userId) {
            return [];
        }

        if (isset($this->propertyIdsCache[$userId])) {
            return $this->propertyIdsCache[$userId];
        }

        /** @var list<array{id: int}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.property) AS id')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return $this->propertyIdsCache[$userId] = array_map(intval(...), array_column($rows, 'id'));
    }

    /**
     * Rôle local de l'utilisateur dans ce logement, ou `null` s'il n'en est
     * pas membre. Ne tient pas compte de `ROLE_ADMIN` : c'est au voter de
     * gérer le bypass global.
     */
    public function findRoleFor(User $user, Property $property): ?PropertyRole
    {
        $member = $this->findOneBy(['user' => $user, 'property' => $property]);

        return $member?->getRole();
    }

    /**
     * Invalide la mémoïsation après une écriture sur les appartenances.
     */
    public function clearPropertyIdsCache(): void
    {
        $this->propertyIdsCache = [];
    }
}
