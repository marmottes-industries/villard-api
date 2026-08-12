<?php

namespace App\Entity;

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
use App\Contract\RoomScopedInterface;
use App\Enum\State;
use App\Repository\InventoryItemRepository;
use App\State\PropertyScopeProcessor;
use App\Validator\RoomBelongsToProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cf. {@see Occupation} pour la logique des expressions de sécurité. La
 * suppression, jadis réservée à `ROLE_ADMIN`, passe au gestionnaire local du
 * logement — `ROLE_ADMIN` la conserve via le bypass du voter.
 *
 * Trois niveaux de rangement, à ne pas confondre : {@see Room} dit dans quelle
 * pièce, {@see self::$location} dit où dans cette pièce, et {@see Category} ne
 * survit que le temps de la transition (cf. le champ).
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
    'room' => 'exact',
    'name' => 'ipartial',
    'category' => 'exact',
    'state' => 'exact',
    'note' => 'ipartial',
    'location' => 'ipartial',
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'quantity', 'state'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: InventoryItemRepository::class)]
#[RoomBelongsToProperty]
class InventoryItem implements RoomScopedInterface
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

    /**
     * @deprecated Remplacée par {@see self::$room}. Devenue nullable le temps
     *             que les deux clients basculent ; le champ et sa colonne
     *             seront retirés à la prochaine version majeure.
     */
    #[ORM\ManyToOne(inversedBy: 'inventoryItems')]
    private ?Category $category = null;

    /**
     * Pièce du logement où se trouve l'article. Nullable : un article peut
     * n'être rattaché à aucune pièce, et la suppression d'une pièce délie ses
     * articles (`ON DELETE SET NULL`) plutôt que d'échouer.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Room $room = null;

    #[ORM\Column(enumType: State::class, options: ['default' => STATE::OK])]
    private State $state = State::OK;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    /**
     * Précision de rangement à l'intérieur de la pièce — « placard du haut »,
     * « sous le lit ». Complète {@see self::$room}, ne la remplace pas.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function setState(State $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }
}
