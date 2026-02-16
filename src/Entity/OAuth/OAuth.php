<?php

namespace App\Entity\OAuth;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\OAuth\OAuthRepository::class)]
#[ORM\Table(name: 'OAUTH')]
#[ORM\HasLifecycleCallbacks]
class OAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer', name: 'idOAuth')]
    private ?int $idOAuth = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'text', name: 'access_token')]
    private string $accessToken = '';

    #[ORM\Column(type: 'text', name: 'refresh_token')]
    private string $refreshToken = '';

    #[ORM\Column(type: 'string', length: 50, name: 'token_type')]
    private string $tokenType = 'Bearer';

    #[ORM\Column(type: 'text', nullable: true, name: 'scope')]
    private ?string $scope = null;

    #[ORM\Column(type: 'datetime', name: 'expires_at')]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = $now;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getIdOAuth(): ?int
    {
        return $this->idOAuth;
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

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function setTokenType(string $tokenType): self
    {
        $this->tokenType = $tokenType;
        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** Whether the access token is expired or will expire in the next 60 seconds */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }
        return $this->expiresAt <= (new \DateTime())->modify('+60 seconds');
    }
}
