<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Contract\PropertyScopedInterface;
use App\Repository\OccupationRepository;
use App\State\PropertyScopeProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Les lectures sont déjà cloisonnées par {@see \App\Doctrine\PropertyScopeExtension} ;
 * le `PROPERTY_VIEW` du `Get` est une seconde barrière assumée, l'extension
 * seule ayant déjà laissé passer des IDOR dans d'autres projets.
 *
 * Au `POST`, un logement nul est toléré : c'est
 * {@see \App\State\PropertyScopeProcessor} qui applique le repli mono-logement
 * puis revérifie l'autorisation, la validation et la sécurité passant avant
 * les processors dans le pipeline d'API Platform.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROPERTY_VIEW', object.getProperty())"),
        new Post(
            securityPostDenormalize: "(object.getProperty() == null or is_granted('PROPERTY_CONTRIBUTE', object.getProperty())) and (is_granted('PROPERTY_MANAGE', object.getProperty()) or object.getOccupant() == user)",
            processor: PropertyScopeProcessor::class,
        ),
        new Put(securityPostDenormalize: "is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and is_granted('PROPERTY_CONTRIBUTE', previous_object.getProperty()) and (is_granted('PROPERTY_MANAGE', previous_object.getProperty()) or (object.getOccupant() == user and previous_object.getOccupant() == user))"),
        new Patch(securityPostDenormalize: "is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and is_granted('PROPERTY_CONTRIBUTE', previous_object.getProperty()) and (is_granted('PROPERTY_MANAGE', previous_object.getProperty()) or (object.getOccupant() == user and previous_object.getOccupant() == user))"),
        new Delete(security: "is_granted('PROPERTY_MANAGE', object.getProperty()) or (is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and object.getOccupant() == user)"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'property' => 'exact',
    'occupant' => 'exact',
    'occupant.uuid' => 'exact',
    'notes' => 'ipartial',
])]
#[ApiFilter(DateFilter::class, properties: ['startDate', 'endDate'])]
#[ApiFilter(OrderFilter::class, properties: ['startDate', 'endDate'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: OccupationRepository::class)]
class Occupation implements PropertyScopedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La date de fin doit être postérieure ou égale à la date de début.')]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Set once the "end of stay" notification has been dispatched, so the daily
     * command stays idempotent and never notifies the same stay twice.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $endNotifiedAt = null;

    #[ORM\ManyToOne(inversedBy: 'occupations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $occupant = null;

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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getEndNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->endNotifiedAt;
    }

    public function setEndNotifiedAt(?\DateTimeImmutable $endNotifiedAt): static
    {
        $this->endNotifiedAt = $endNotifiedAt;

        return $this;
    }

    public function getOccupant(): ?User
    {
        return $this->occupant;
    }

    public function setOccupant(?User $occupant): static
    {
        $this->occupant = $occupant;

        return $this;
    }
}
