<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\DeviceTokenRepository;
use App\State\DeviceTokenProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A push notification target registered by a client (one row per device/install).
 * The `token` is an Expo push token ("ExponentPushToken[…]"). Registration is an
 * upsert by token handled in {@see DeviceTokenProcessor}, so a client can POST the
 * same token repeatedly without creating duplicates.
 */
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN') or object.getOwner() == user"),
        new Post(
            securityPostDenormalize: "is_granted('ROLE_USER')",
            processor: DeviceTokenProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_ADMIN') or object.getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['device_token:read']],
    denormalizationContext: ['groups' => ['device_token:write']],
)]
#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_DEVICE_TOKEN', fields: ['token'])]
class DeviceToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['device_token:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['device_token:read', 'device_token:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $token = null;

    #[ORM\Column(length: 16)]
    #[Groups(['device_token:read', 'device_token:write'])]
    #[Assert\Choice(choices: ['ios', 'android', 'web'])]
    private ?string $platform = null;

    #[ORM\ManyToOne(inversedBy: 'deviceTokens')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(writable: false)]
    #[Groups(['device_token:read'])]
    private ?User $owner = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    #[Groups(['device_token:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[ApiProperty(writable: false)]
    #[Groups(['device_token:read'])]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
