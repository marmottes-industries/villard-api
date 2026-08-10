<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\PropertyRole;
use App\Repository\PropertyMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Appartenance d'un utilisateur à un logement, avec son rôle local.
 * C'est la seule source de vérité du cloisonnement : ni l'extension Doctrine
 * ni le voter ne regardent autre chose.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object.getProperty())"),
        new Post(securityPostDenormalize: "is_granted('PROPERTY_MANAGE', object.getProperty())"),
        new Patch(securityPostDenormalize: "is_granted('PROPERTY_MANAGE', object.getProperty()) and is_granted('PROPERTY_MANAGE', previous_object.getProperty())"),
        new Delete(security: "is_granted('PROPERTY_MANAGE', object.getProperty())"),
    ],
    normalizationContext: ['groups' => ['member:read']],
    denormalizationContext: ['groups' => ['member:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'property' => 'exact',
    'user' => 'exact',
    'user.uuid' => 'exact',
    'role' => 'exact',
])]
#[ORM\Entity(repositoryClass: PropertyMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PROPERTY_MEMBER', fields: ['property', 'user'])]
#[UniqueEntity(fields: ['property', 'user'], message: 'Cet utilisateur est déjà membre de ce logement.')]
class PropertyMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['member:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['member:read', 'member:write', 'property:summary'])]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    // Volontairement hors du groupe `user:read` : `/api/me` sérialise
    // User → memberships → PropertyMember, et rattacher l'utilisateur ici
    // refermerait le cycle sur lui-même.
    #[Groups(['member:read', 'member:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 32, enumType: PropertyRole::class, options: ['default' => PropertyRole::OCCUPANT])]
    #[Groups(['member:read', 'member:write', 'property:summary'])]
    private PropertyRole $role = PropertyRole::OCCUPANT;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): PropertyRole
    {
        return $this->role;
    }

    public function setRole(PropertyRole $role): static
    {
        $this->role = $role;

        return $this;
    }
}
