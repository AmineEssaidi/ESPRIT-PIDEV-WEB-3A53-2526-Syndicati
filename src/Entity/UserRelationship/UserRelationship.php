<?php
namespace App\Entity\UserRelationship;

use App\Entity\User\User;
use App\Repository\UserRelationship\UserRelationshipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRelationshipRepository::class)]
#[ORM\Table(name: 'user_relationship')]
#[ORM\UniqueConstraint(name: 'UQ_rel', columns: ['user_first_id', 'user_second_id'])]
#[ORM\HasLifecycleCallbacks]
class UserRelationship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_first_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user_first = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_second_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $user_second = null;

    #[ORM\Column(type: 'string', length: 50, columnDefinition: "ENUM('PENDING_FIRST_SECOND', 'PENDING_SECOND_FIRST', 'FRIENDS', 'BLOCKED_FIRST_SECOND', 'BLOCKED_SECOND_FIRST')")]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserFirst(): ?User
    {
        return $this->user_first;
    }

    public function setUserFirst(?User $user_first): self
    {
        $this->user_first = $user_first;
        return $this;
    }

    public function getUserSecond(): ?User
    {
        return $this->user_second;
    }

    public function setUserSecond(?User $user_second): self
    {
        $this->user_second = $user_second;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
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
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
