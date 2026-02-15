<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\PublicationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'publication')] // Assuming table name is 'publication' based on context (cols are suffix _pub)
#[ORM\HasLifecycleCallbacks]
class Publication
{
    public const CATEGORIES = ['Announcement', 'Suggestion', 'Jeux Video', 'Informatique', 'Nouveauté', 'Discussion General', 'Culture', 'Sport'];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, minMessage: "The title must be at least 5 characters long.")]
    private $titre_pub;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, minMessage: "The description must be at least 10 characters long.")]
    private $description_pub;

    #[ORM\Column(type: 'datetime')]
    private $date_creation_pub;

    #[ORM\Column(type: 'string', length: 255, columnDefinition: "ENUM('Announcement', 'Suggestion', 'Jeux Video', 'Informatique', 'Nouveauté', 'Discussion General', 'Culture', 'Sport')")]
    #[Assert\Choice(choices: self::CATEGORIES)]
    private $categorie_pub;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $image_pub;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitrePub(): ?string
    {
        return $this->titre_pub;
    }

    public function setTitrePub(string $titre_pub): self
    {
        $this->titre_pub = $titre_pub;

        return $this;
    }

    public function getDescriptionPub(): ?string
    {
        return $this->description_pub;
    }

    public function setDescriptionPub(string $description_pub): self
    {
        $this->description_pub = $description_pub;

        return $this;
    }

    public function getDateCreationPub(): ?\DateTimeInterface
    {
        return $this->date_creation_pub;
    }

    public function setDateCreationPub(\DateTimeInterface $date_creation_pub): self
    {
        $this->date_creation_pub = $date_creation_pub;

        return $this;
    }

    public function getCategoriePub(): ?string
    {
        return $this->categorie_pub;
    }

    public function setCategoriePub(string $categorie_pub): self
    {
        $this->categorie_pub = $categorie_pub;

        return $this;
    }

    public function getImagePub(): ?string
    {
        return $this->image_pub;
    }

    public function setImagePub(?string $image_pub): self
    {
        $this->image_pub = $image_pub;

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
        if ($this->date_creation_pub === null) {
            $this->date_creation_pub = new \DateTime();
        }
    }
}
