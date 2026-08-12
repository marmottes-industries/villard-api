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
use App\Enum\RoomType;
use App\Repository\RoomRepository;
use App\State\PropertyScopeProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une pièce d'un logement — « Chambre 1 », « Salle de bain étage », « Cabane à
 * skis ». Elle porte le rangement structurant de l'inventaire, là où
 * {@see InventoryItem::$location} reste la précision libre à l'intérieur de la
 * pièce (« placard du haut »).
 *
 * Contrairement à {@see Category}, restée commune à tous les logements pour les
 * courses, une pièce est propre à son logement : c'est la seule façon d'avoir
 * deux chambres distinctes ici et une seule ailleurs.
 *
 * Écritures réservées au gestionnaire local, comme {@see PropertyMember} — la
 * structure d'un logement n'est pas modifiable par ses occupants.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object.getProperty())"),
        /*
         * Pas d'échappatoire `object.getProperty() == null` ici, contrairement
         * aux cinq ressources métier historiques : elle n'existe chez elles que
         * pour les builds mobiles antérieurs au multi-logements, et aucun
         * client installé ne poste de pièce. La conserver dégraderait
         * silencieusement `MANAGE` en `CONTRIBUTE` — un occupant mono-logement
         * passerait par la branche nulle, puis le repli de
         * {@see PropertyScopeProcessor} ne revérifie que `CONTRIBUTE`.
         */
        new Post(
            securityPostDenormalize: "is_granted('PROPERTY_MANAGE', object.getProperty())",
            processor: PropertyScopeProcessor::class,
        ),
        new Put(securityPostDenormalize: "is_granted('PROPERTY_MANAGE', object.getProperty()) and is_granted('PROPERTY_MANAGE', previous_object.getProperty())"),
        new Patch(securityPostDenormalize: "is_granted('PROPERTY_MANAGE', object.getProperty()) and is_granted('PROPERTY_MANAGE', previous_object.getProperty())"),
        new Delete(security: "is_granted('PROPERTY_MANAGE', object.getProperty())"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'property' => 'exact',
    'name' => 'ipartial',
    'type' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['archived'])]
#[ApiFilter(OrderFilter::class, properties: ['position', 'name'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ROOM_PROPERTY_NAME', fields: ['property', 'name'])]
#[UniqueEntity(fields: ['property', 'name'], message: 'Cette pièce existe déjà dans ce logement.')]
class Room implements PropertyScopedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 32, nullable: true, enumType: RoomType::class)]
    private ?RoomType $type = null;

    /**
     * Ordre d'affichage dans le logement. Un tri alphabétique placerait
     * « Chambre 10 » avant « Chambre 2 » ; c'est le gestionnaire qui décide.
     */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?RoomType
    {
        return $this->type;
    }

    public function setType(?RoomType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
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
}
