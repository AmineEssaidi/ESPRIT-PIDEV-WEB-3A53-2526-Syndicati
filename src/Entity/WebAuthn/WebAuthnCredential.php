<?php

namespace App\Entity\WebAuthn;

use App\Entity\User\User;
use App\Repository\WebAuthn\WebAuthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

#[ORM\Entity(repositoryClass: WebAuthnCredentialRepository::class)]
#[ORM\Table(name: 'WebAuthnCred')]
class WebAuthnCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_webauthn')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $credentialId = null;

    #[ORM\Column(type: 'text')]
    private ?string $publicKey = null;

    #[ORM\Column]
    private ?int $signCount = null;

    #[ORM\Column(type: 'json')]
    private array $transports = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lastUsedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCredentialId(): ?string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;

        return $this;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getSignCount(): ?int
    {
        return $this->signCount;
    }

    public function setSignCount(int $signCount): self
    {
        $this->signCount = $signCount;

        return $this;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports): self
    {
        $this->transports = $transports;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTimeInterface $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function toPublicKeyCredentialSource(): PublicKeyCredentialSource
    {
        $trustPath = new EmptyTrustPath();
        $aaguid = Uuid::fromString('00000000-0000-0000-0000-000000000000'); // Default AAGUID

        // Decode base64url fields
        $rawId = base64_decode(str_replace(['-', '_'], ['+', '/'], $this->credentialId) . str_repeat('=', (4 - strlen($this->credentialId) % 4) % 4));
        $rawPublicKey = base64_decode(str_replace(['-', '_'], ['+', '/'], $this->publicKey) . str_repeat('=', (4 - strlen($this->publicKey) % 4) % 4));

        return new PublicKeyCredentialSource(
            $rawId,
            'public-key',
            $this->transports,
            'none', // attestationType
            $trustPath,
            $aaguid,
            $rawPublicKey,
            (string) $this->user->getIdUser(),
            $this->signCount
        );
    }
}
