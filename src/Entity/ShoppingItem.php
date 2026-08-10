<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Contract\PropertyScopedInterface;
use App\Repository\ShoppingItemRepository;
use App\State\PropertyScopeProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cf. {@see Occupation} pour la logique des expressions de sécurité. La
 * suppression, jadis réservée à `ROLE_ADMIN`, passe au gestionnaire local du
 * logement — `ROLE_ADMIN` la conserve via le bypass du voter.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object.getProperty())"),
        new Post(
            securityPostDenormalize: "object.getProperty() == null or is_granted('PROPERTY_CONTRIBUTE', object.getProperty())",
            processor: PropertyScopeProcessor::class,
        ),
        new Put(securityPostDenormalize: "is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and is_granted('PROPERTY_CONTRIBUTE', previous_object.getProperty())"),
        new Patch(securityPostDenormalize: "is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and is_granted('PROPERTY_CONTRIBUTE', previous_object.getProperty())"),
        new Delete(security: "is_granted('PROPERTY_MANAGE', object.getProperty())"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'property' => 'exact',
    'name' => 'ipartial',
    'category' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['purchased'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'purchased'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: ShoppingItemRepository::class)]
class ShoppingItem implements PropertyScopedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $purchased = null;

    #[ORM\ManyToOne(inversedBy: 'shoppingItems')]
    private ?Category $category = null;

    /**
     * Volontairement sans `Assert\NotNull` : au `POST`, le repli mono-logement
     * de {@see \App\State\PropertyScopeProcessor} renseigne le champ quand le
     * client l'omet, et la validation passerait avant lui.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function isPurchased(): ?bool
    {
        return $this->purchased;
    }

    public function setPurchased(bool $purchased): static
    {
        $this->purchased = $purchased;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
