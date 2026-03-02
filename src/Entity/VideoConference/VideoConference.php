<?php

namespace App\Entity\VideoConference;

use App\Entity\User\User;
use App\Repository\VideoConference\VideoConferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoConferenceRepository::class)]
#[ORM\Table(name: 'VideoConference')]
class VideoConference
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $idvidconf = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $roomToken = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $expires_at = null;

    #[ORM\Column(type: 'string', columnDefinition: "SET('pending', 'live', 'ended', '')")]
    private ?string $status = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $roomName = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user = null;

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function setRoomName(?string $roomName): self
    {
        $this->roomName = $roomName;
        return $this;
    }

    public function getIdvidconf(): ?int
    {
        return $this->idvidconf;
    }

    public function getRoomToken(): ?string
    {
        return $this->roomToken;
    }

    public function setRoomToken(string $roomToken): self
    {
        $this->roomToken = $roomToken;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpiresAt(\DateTimeInterface $expires_at): self
    {
        $this->expires_at = $expires_at;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
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
}
