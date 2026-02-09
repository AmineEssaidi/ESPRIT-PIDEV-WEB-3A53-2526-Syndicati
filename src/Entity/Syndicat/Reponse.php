<?php

namespace App\Entity\Syndicat;

use App\Entity\User\User;
use App\Repository\Syndicat\ReponseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReponseRepository::class)]
#[ORM\Table(name: 'reponses')]
#[ORM\HasLifecycleCallbacks]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'idreponses')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, name: 'titrereponse', nullable: true)]
    private $titrereponse;

    #[ORM\Column(type: 'string', length: 255, name: 'messagereponse')]
    #[Assert\NotBlank]
    private $messagereponse;

    #[ORM\Column(type: 'string', length: 255, name: 'imagereponse', nullable: true)]
    private $imagereponse;

    #[ORM\ManyToOne(targetEntity: Reclamation::class)]
    #[ORM\JoinColumn(name: 'reclamation_id', referencedColumnName: 'idreclamations', nullable: false)]
    private $reclamation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private $created_at;

    #[ORM\Column(type: 'datetime', name: 'updated_at')]
    private $updated_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitrereponse(): ?string
    {
        return $this->titrereponse;
    }

    public function setTitrereponse(?string $titrereponse): self
    {
        $this->titrereponse = $titrereponse;
        return $this;
    }

    public function getMessagereponse(): ?string
    {
        return $this->messagereponse;
    }

    public function setMessagereponse(string $messagereponse): self
    {
        $this->messagereponse = $messagereponse;
        return $this;
    }

    public function getImagereponse(): ?string
    {
        return $this->imagereponse;
    }

    public function setImagereponse(?string $imagereponse): self
    {
        $this->imagereponse = $imagereponse;
        return $this;
    }

    public function getReclamation(): ?Reclamation
    {
        return $this->reclamation;
    }

    public function setReclamation(?Reclamation $reclamation): self
    {
        $this->reclamation = $reclamation;
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
