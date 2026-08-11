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
use App\Enum\AccentColor;
use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un logement géré par l'application. Toutes les ressources métier
 * (`Occupation`, `Work`, `InventoryItem`, `ShoppingItem`, `Note`) lui sont
 * rattachées via {@see \App\Contract\PropertyScopedInterface}.
 *
 * Le logement porte aussi ses propres coordonnées météo : le point principal
 * (le logement lui-même) et un point secondaire optionnel, par exemple un
 * domaine skiable en altitude.
 *
 * `Category` reste volontairement commune à tous les logements.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object)"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('PROPERTY_MANAGE', object)"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['property:read']],
    denormalizationContext: ['groups' => ['property:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial', 'slug' => 'exact', 'city' => 'ipartial'])]
#[ApiFilter(BooleanFilter::class, properties: ['archived'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'city'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé par un autre logement.')]
class Property
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['property:read', 'property:summary'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug ne peut contenir que des minuscules, des chiffres et des tirets.')]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $address = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private ?float $latitude = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private ?float $longitude = null;

    #[ORM\Column(length: 64, options: ['default' => 'Europe/Paris'])]
    #[Assert\NotBlank]
    #[Assert\Timezone]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private string $timezone = 'Europe/Paris';

    /**
     * Point météo secondaire optionnel, typiquement un domaine d'altitude
     * (« Côte 2000 » pour Villard-de-Lans). Les trois champs vont ensemble.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $secondaryLocationName = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['property:read', 'property:write'])]
    private ?float $secondaryLatitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['property:read', 'property:write'])]
    private ?float $secondaryLongitude = null;

    /**
     * Couleur d'accent du logement. Fait partie du résumé : le sélecteur de
     * logement et la teinte de l'interface s'appuient dessus dès `/api/me`,
     * sans second appel.
     */
    #[ORM\Column(length: 32, enumType: AccentColor::class, options: ['default' => AccentColor::FOREST])]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private AccentColor $accentColor = AccentColor::FOREST;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['property:read', 'property:write', 'property:summary'])]
    private bool $archived = false;

    /**
     * @var Collection<int, PropertyMember>
     */
    #[ORM\OneToMany(targetEntity: PropertyMember::class, mappedBy: 'property', cascade: ['persist', 'remove'])]
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getSecondaryLocationName(): ?string
    {
        return $this->secondaryLocationName;
    }

    public function setSecondaryLocationName(?string $secondaryLocationName): static
    {
        $this->secondaryLocationName = $secondaryLocationName;

        return $this;
    }

    public function getSecondaryLatitude(): ?float
    {
        return $this->secondaryLatitude;
    }

    public function setSecondaryLatitude(?float $secondaryLatitude): static
    {
        $this->secondaryLatitude = $secondaryLatitude;

        return $this;
    }

    public function getSecondaryLongitude(): ?float
    {
        return $this->secondaryLongitude;
    }

    public function setSecondaryLongitude(?float $secondaryLongitude): static
    {
        $this->secondaryLongitude = $secondaryLongitude;

        return $this;
    }

    /**
     * Le point secondaire n'est exploitable que si ses trois champs sont
     * renseignés — sinon il est simplement ignoré par le client météo.
     */
    public function hasSecondaryLocation(): bool
    {
        return null !== $this->secondaryLocationName
            && null !== $this->secondaryLatitude
            && null !== $this->secondaryLongitude;
    }

    public function getAccentColor(): AccentColor
    {
        return $this->accentColor;
    }

    public function setAccentColor(AccentColor $accentColor): static
    {
        $this->accentColor = $accentColor;

        return $this;
    }

    /**
     * Hexadécimal correspondant à l'accent, exposé en lecture seule : les
     * clients teintent leur interface sans dupliquer la table de la palette.
     */
    #[Groups(['property:read', 'property:summary'])]
    public function getAccentHex(): string
    {
        return $this->accentColor->getHex();
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

    /**
     * @return Collection<int, PropertyMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(PropertyMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setProperty($this);
        }

        return $this;
    }

    public function removeMember(PropertyMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getProperty() === $this) {
                $member->setProperty(null);
            }
        }

        return $this;
    }
}
