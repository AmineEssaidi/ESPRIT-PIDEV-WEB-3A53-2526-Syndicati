<?php

namespace App\Entity\VideoConference;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'VideoConferenceParticipant')]
class VideoConferenceParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VideoConference::class)]
    #[ORM\JoinColumn(name: 'conference_id', referencedColumnName: 'idvidconf', nullable: false, onDelete: 'CASCADE')]
    private ?VideoConference $conference = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $guestName = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastActive = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signalingData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConference(): ?VideoConference
    {
        return $this->conference;
    }

    public function setConference(?VideoConference $conference): self
    {
        $this->conference = $conference;
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

    public function getGuestName(): ?string
    {
        return $this->guestName;
    }

    public function setGuestName(?string $guestName): self
    {
        $this->guestName = $guestName;
        return $this;
    }

    public function getLastActive(): ?\DateTimeInterface
    {
        return $this->lastActive;
    }

    public function setLastActive(\DateTimeInterface $lastActive): self
    {
        $this->lastActive = $lastActive;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getSignalingData(): ?string
    {
        return $this->signalingData;
    }

    public function setSignalingData(?string $signalingData): self
    {
        $this->signalingData = $signalingData;
        return $this;
    }
}
