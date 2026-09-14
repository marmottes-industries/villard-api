<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Repository\ImageRepository;
use App\State\ImageUploadProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Photo jointe à une {@see Note} ou à des {@see Work}.
 *
 * Volontairement réduite à deux opérations : les images se lisent embarquées
 * dans leur parent (`Note.images`, `Work.images`), qui porte déjà le
 * cloisonnement par logement. Pas de `Get` ni de `GetCollection`, donc pas
 * besoin de {@see \App\Contract\PropertyScopedInterface}.
 *
 * Le fichier n'est jamais servi par un chemin public : `url` est une URL signée
 * à durée limitée, ajoutée par {@see \App\Serializer\ImageNormalizer} et
 * vérifiée par {@see \App\Controller\ImageFileController}.
 *
 * Les droits d'écriture reprennent la règle du `PATCH` du parent : manager du
 * logement, ou contributeur auteur du parent. Le `POST` ne peut pas l'exprimer
 * en attribut (pas de désérialisation, donc pas d'objet à tester) :
 * {@see ImageUploadProcessor} la vérifie lui-même.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new OpenApiOperation(
                summary: 'Joint une image à une note ou à des travaux.',
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                    'note' => ['type' => 'string', 'example' => '/api/notes/1'],
                                    'work' => ['type' => 'string', 'example' => '/api/works/1'],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            security: "is_granted('ROLE_USER')",
            deserialize: false,
            processor: ImageUploadProcessor::class,
        ),
        new Delete(security: "is_granted('PROPERTY_MANAGE', object.getProperty()) or (is_granted('PROPERTY_CONTRIBUTE', object.getProperty()) and object.getParentAuthor() == user)"),
    ]
)]
#[ORM\Entity(repositoryClass: ImageRepository::class)]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * `onDelete: CASCADE` n'est qu'un filet côté base : la suppression normale
     * passe par la cascade ORM de `Note::$images`, seule à déclencher
     * {@see \App\Doctrine\ImageFileRemover} et donc à effacer le fichier.
     */
    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[ApiProperty(readable: false, writable: false)]
    private ?Note $note = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[ApiProperty(readable: false, writable: false)]
    private ?Work $work = null;

    /** Nom du fichier dans le dossier de stockage, aléatoire. */
    #[ORM\Column(length: 64)]
    #[ApiProperty(readable: false, writable: false)]
    private ?string $storedName = null;

    #[ORM\Column(length: 32)]
    #[ApiProperty(writable: false)]
    private ?string $mimeType = null;

    /** Taille stockée, en octets (après compression éventuelle). */
    #[ORM\Column]
    #[ApiProperty(writable: false)]
    private ?int $size = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    private ?int $width = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    private ?int $height = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?Note
    {
        return $this->note;
    }

    public function setNote(?Note $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getWork(): ?Work
    {
        return $this->work;
    }

    public function setWork(?Work $work): static
    {
        $this->work = $work;

        return $this;
    }

    #[Ignore]
    public function getParent(): Note|Work|null
    {
        return $this->note ?? $this->work;
    }

    /** Logement du parent, pour les expressions de sécurité. */
    #[Ignore]
    public function getProperty(): ?Property
    {
        return $this->getParent()?->getProperty();
    }

    /** Auteur du parent, pour les expressions de sécurité. */
    #[Ignore]
    public function getParentAuthor(): ?User
    {
        return $this->getParent()?->getAuthor();
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

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
}
