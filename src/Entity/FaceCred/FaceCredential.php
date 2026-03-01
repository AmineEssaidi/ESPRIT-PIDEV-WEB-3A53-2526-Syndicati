<?php

namespace App\Entity\FaceCred;

use App\Entity\User\User;
use App\Repository\FaceCred\FaceCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FaceCredentialRepository::class)]
#[ORM\Table(name: 'facecred')]
class FaceCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_facecred')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'device_id', length: 255)]
    private ?string $deviceId = null;

    #[ORM\Column(name: 'encrypted_faceid', type: 'blob')]
    private $encryptedFaceid = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'last_used_at', type: 'datetime')]
    private ?\DateTimeInterface $lastUsedAt = null;

    /**
     * Note: Using string to map MySQL SET type.
     */
    #[ORM\Column(name: 'flag', type: 'string', length: 50)]
    private ?string $flag = 'active';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): self
    {
        $this->deviceId = $deviceId;
        return $this;
    }

    /**
     * Doctrine blob can be returned as a stream resource (e.g. MySQL); always return string for decrypt().
     */
    public function getEncryptedFaceid(): ?string
    {
        $value = $this->encryptedFaceid;
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        return (string) $value;
    }

    public function setEncryptedFaceid($encryptedFaceid): self
    {
        $this->encryptedFaceid = $encryptedFaceid;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
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

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(string $flag): self
    {
        $this->flag = $flag;
        return $this;
    }
}
