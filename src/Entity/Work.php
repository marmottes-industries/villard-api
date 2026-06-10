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
use App\Enum\WorkPriority;
use App\Enum\WorkStatus;
use App\Enum\WorkType;
use App\Repository\WorkRepository;
use App\State\WorkProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(
            securityPostDenormalize: "is_granted('ROLE_ADMIN') or object.getAuthor() == user",
            processor: WorkProcessor::class,
        ),
        new Put(securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.getAuthor() == user and previous_object.getAuthor() == user)",
            processor: WorkProcessor::class,
        ),
        new Patch(securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.getAuthor() == user and previous_object.getAuthor() == user)",
            processor: WorkProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_ADMIN') or object.getAuthor() == user"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'ipartial',
    'description' => 'ipartial',
    'author.uuid' => 'exact',
    'status' => 'exact',
    'type' => 'exact',
    'priority' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'scheduledFor'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'scheduledFor', 'priority', 'status'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: WorkRepository::class)]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: WorkStatus::class)]
    private WorkStatus $status = WorkStatus::SUGGESTED;

    #[ORM\Column(nullable: true, enumType: WorkType::class)]
    private ?WorkType $type = null;

    #[ORM\Column(nullable: true, enumType: WorkPriority::class)]
    private ?WorkPriority $priority = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledFor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $estimatedCost = null; // in euro

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $actualCost = null; // in euro

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): WorkStatus
    {
        return $this->status;
    }

    public function setStatus(WorkStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): ?WorkType
    {
        return $this->type;
    }

    public function setType(?WorkType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPriority(): ?WorkPriority
    {
        return $this->priority;
    }

    public function setPriority(?WorkPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getScheduledFor(): ?\DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(?\DateTimeImmutable $scheduledFor): static
    {
        $this->scheduledFor = $scheduledFor;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getEstimatedCost(): ?int
    {
        return $this->estimatedCost;
    }

    public function setEstimatedCost(?int $estimatedCost): static
    {
        $this->estimatedCost = $estimatedCost;

        return $this;
    }

    public function getActualCost(): ?int
    {
        return $this->actualCost;
    }

    public function setActualCost(?int $actualCost): static
    {
        $this->actualCost = $actualCost;

        return $this;
    }
}
