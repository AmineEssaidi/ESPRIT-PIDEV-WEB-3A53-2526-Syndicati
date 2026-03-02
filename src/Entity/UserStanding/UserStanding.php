<?php
namespace App\Entity\UserStanding;

use App\Entity\User\User;
use App\Repository\UserStanding\UserStandingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserStandingRepository::class)]
#[ORM\Table(name: 'user_standing')]
#[ORM\HasLifecycleCallbacks]
class UserStanding
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $level = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $points = 0;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'NORMAL'], columnDefinition: "ENUM('NORMAL', 'WARNED', 'SUSPENDED', 'BANNED') DEFAULT 'NORMAL'")]
    private string $standing_label = 'NORMAL';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updated_at = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getStandingLabel(): string
    {
        return $this->standing_label;
    }

    public function setStandingLabel(string $standing_label): self
    {
        $this->standing_label = $standing_label;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!$this->updated_at) {
            $this->updated_at = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
