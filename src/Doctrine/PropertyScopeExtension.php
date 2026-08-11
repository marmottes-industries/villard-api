<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Contract\PropertyScopedInterface;
use App\Entity\Property;
use App\Entity\PropertyMember;
use App\Entity\User;
use App\Repository\PropertyMemberRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Cloisonne toutes les requêtes Doctrine d'API Platform sur les logements dont
 * l'utilisateur courant est membre.
 *
 * S'applique aux collections **et** aux items : la seule extension de
 * collection laisserait `GET /api/notes/42` accessible à n'importe qui
 * (IDOR). Les écritures, elles, restent gardées par
 * {@see \App\Security\Voter\PropertyVoter}.
 *
 * Trois familles de ressources sont couvertes :
 *  - celles qui implémentent {@see PropertyScopedInterface} → filtre direct
 *    sur `property` ;
 *  - `Property` → filtre sur l'existence d'une appartenance ;
 *  - `PropertyMember` → filtre sur le logement de l'appartenance.
 *
 * `ROLE_ADMIN` court-circuite le filtre et voit l'ensemble des logements, ce
 * qui préserve les capacités d'administration antérieures au multi-logements.
 */
final readonly class PropertyScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
        private PropertyMemberRepository $members,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        $field = $this->scopedField($resourceClass);

        if (null === $field) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('userProperties');
        $propertyIds = $this->members->findPropertyIdsForUser($user);

        // `IN ()` avec un tableau vide est invalide en SQL ; Doctrine le
        // traduit en `IN (NULL)`, ce qui ne renvoie rien — c'est bien le
        // comportement attendu pour un utilisateur sans aucun logement.
        $queryBuilder
            ->andWhere(\sprintf('%s.%s IN (:%s)', $alias, $field, $parameter))
            ->setParameter($parameter, $propertyIds);
    }

    /**
     * Nom du champ portant le logement pour cette ressource, ou `null` si la
     * ressource n'est pas cloisonnée.
     *
     * @param class-string $resourceClass
     */
    private function scopedField(string $resourceClass): ?string
    {
        if (is_a($resourceClass, PropertyScopedInterface::class, true)) {
            return 'property';
        }

        if (Property::class === $resourceClass) {
            return 'id';
        }

        if (PropertyMember::class === $resourceClass) {
            return 'property';
        }

        return null;
    }
}
