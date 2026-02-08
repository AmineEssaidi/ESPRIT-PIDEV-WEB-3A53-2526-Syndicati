<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\CommentaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer')]
    private $id_commentaire;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private $description_commentaire;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $image_commentaire;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime')]
    private $updated_at;

    #[ORM\Column(type: 'boolean')]
    private $visibility; // true = public, false = anonymous

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'id_pub', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $publication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    public function getIdCommentaire(): ?int
    {
        return $this->id_commentaire;
    }

    public function getDescriptionCommentaire(): ?string
    {
        return $this->description_commentaire;
    }

    public function setDescriptionCommentaire(string $description_commentaire): self
    {
        $this->description_commentaire = $description_commentaire;

        return $this;
    }

    public function getImageCommentaire(): ?string
    {
        return $this->image_commentaire;
    }

    public function setImageCommentaire(?string $image_commentaire): self
    {
        $this->image_commentaire = $image_commentaire;

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

    public function isVisibility(): ?bool
    {
        return $this->visibility;
    }

    public function setVisibility(bool $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->created_at === null) {
            $this->created_at = new \DateTime();
        }
        if ($this->updated_at === null) {
            $this->updated_at = new \DateTime();
        }
        if ($this->visibility === null) {
            $this->visibility = true; // Default to public
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
