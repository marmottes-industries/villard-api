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
use App\Repository\NoteRepository;
use App\State\NoteProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(
            securityPostDenormalize: "is_granted('ROLE_ADMIN') or object.getAuthor() == user",
            processor: NoteProcessor::class,
        ),
        new Put(securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.getAuthor() == user and previous_object.getAuthor() == user)"),
        new Patch(securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.getAuthor() == user and previous_object.getAuthor() == user)"),
        new Delete(security: "is_granted('ROLE_ADMIN') or object.getAuthor() == user"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'ipartial',
    'content' => 'ipartial',
    'author' => 'exact',
    'author.uuid' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'title'], arguments: ['orderParameterName' => 'order'])]
#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }
}
