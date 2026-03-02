<?php

namespace App\Entity\Log;

use App\Repository\Log\AppEventLogRepository;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppEventLogRepository::class)]
#[ORM\Table(name: 'app_event_log')]
#[ORM\Index(name: 'IDX_event_type_created', columns: ['event_type', 'created_at'])]
#[ORM\Index(name: 'IDX_event_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'IDX_event_user_created', columns: ['user_id', 'created_at'])]
class AppEventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $event_type = null;

    #[ORM\Column(length: 50)]
    private ?string $entity_type = null;

    #[ORM\Column(nullable: true)]
    private ?int $entity_id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?string
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

    public function getEventType(): ?string
    {
        return $this->event_type;
    }

    public function setEventType(string $event_type): self
    {
        $this->event_type = $event_type;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entity_type;
    }

    public function setEntityType(string $entity_type): self
    {
        $this->entity_type = $entity_type;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entity_id;
    }

    public function setEntityId(?int $entity_id): self
    {
        $this->entity_id = $entity_id;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
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
}
