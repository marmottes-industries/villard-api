<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\UserRepository;
use App\State\MeProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            uriTemplate: '/me',
            security: "is_granted('ROLE_USER')",
            name: 'me',
            provider: MeProvider::class,
        ),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Patch(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['user:read'])]
    private Uuid $uuid;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[Groups(['user:read'])]
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Occupation>
     */
    #[ORM\OneToMany(targetEntity: Occupation::class, mappedBy: 'occupant', cascade: ['persist', 'remove'])]
    private Collection $occupations;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\OneToMany(targetEntity: Note::class, mappedBy: 'author')]
    private Collection $notes;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->occupations = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return (string) $this->uuid;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Used by Symfony security as the user's unique identifier. Returns the immutable UUID
     * so renaming the username does not invalidate existing JWTs.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->uuid;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection<int, Occupation>
     */
    public function getOccupations(): Collection
    {
        return $this->occupations;
    }

    public function addOccupation(Occupation $occupation): static
    {
        if (!$this->occupations->contains($occupation)) {
            $this->occupations->add($occupation);
            $occupation->setOccupant($this);
        }

        return $this;
    }

    public function removeOccupation(Occupation $occupation): static
    {
        if ($this->occupations->removeElement($occupation)) {
            // set the owning side to null (unless already changed)
            if ($occupation->getOccupant() === $this) {
                $occupation->setOccupant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(Note $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setAuthor($this);
        }

        return $this;
    }

    public function removeNote(Note $note): static
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getAuthor() === $this) {
                $note->setAuthor(null);
            }
        }

        return $this;
    }
}
