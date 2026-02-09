<?php

namespace App\Entity\Evenement;

use App\Entity\User\User;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
#[ORM\HasLifecycleCallbacks]
class Evenement
{
    public const STATUTS = ['planifie', 'en_cours', 'termine', 'annule'];
    public const TYPES = ['reunion', 'social', 'formation', 'maintenance', 'culturel', 'sportif'];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'id_event')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private $titre_event;

    #[ORM\Column(type: 'string', length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 50, max: 500, minMessage: "The description must be at least 50 characters long.")]
    private $description_event;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    private $date_event;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255, minMessage: "The location must be at least 3 characters long.", maxMessage: "The location cannot be longer than 255 characters.")]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\s.,!?'\"-]*$/",
        message: "The location can only contain letters, numbers, spaces, and common punctuation."
    )]
    private $lieu_event;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotBlank(message: "The number of places cannot be blank.")]
    #[Assert\Type(type: 'integer', message: "This value must be a number.")]
    #[Assert\PositiveOrZero(message: "The number of places must be a positive number or zero.")]
    private $nb_places;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotBlank(message: "The number of remaining places cannot be blank.")]
    #[Assert\Type(type: 'integer', message: "This value must be a number.")]
    #[Assert\PositiveOrZero(message: "The number of remaining places must be a positive number or zero.")]
    private $nb_restants;

    #[ORM\Column(type: 'string', length: 20, columnDefinition: "ENUM('planifie', 'en_cours', 'termine', 'annule')")]
    #[Assert\Choice(choices: self::STATUTS)]
    private $statut_event = 'planifie';

    #[ORM\Column(type: 'string', length: 255)]
    private $image_event;

    #[ORM\Column(type: 'string', length: 50, columnDefinition: "ENUM('reunion', 'social', 'formation', 'maintenance', 'culturel', 'sportif')")]
    #[Assert\Choice(choices: self::TYPES)]
    private $type_event;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime')]
    private $edited_at;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitreEvent(): ?string
    {
        return $this->titre_event;
    }

    public function setTitreEvent(string $titre_event): self
    {
        $this->titre_event = $titre_event;
        return $this;
    }

    public function getDescriptionEvent(): ?string
    {
        return $this->description_event;
    }

    public function setDescriptionEvent(string $description_event): self
    {
        $this->description_event = $description_event;
        return $this;
    }

    public function getDateEvent(): ?\DateTimeInterface
    {
        return $this->date_event;
    }

    public function setDateEvent(\DateTimeInterface $date_event): self
    {
        $this->date_event = $date_event;
        return $this;
    }

    public function getLieuEvent(): ?string
    {
        return $this->lieu_event;
    }

    public function setLieuEvent(string $lieu_event): self
    {
        $this->lieu_event = $lieu_event;
        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nb_places;
    }

    public function setNbPlaces(?int $nb_places): self
    {
        $this->nb_places = $nb_places;
        return $this;
    }

    public function getNbRestants(): ?int
    {
        return $this->nb_restants;
    }

    public function setNbRestants(?int $nb_restants): self
    {
        $this->nb_restants = $nb_restants;
        return $this;
    }

    public function getStatutEvent(): ?string
    {
        return $this->statut_event;
    }

    public function setStatutEvent(?string $statut_event): self
    {
        $this->statut_event = $statut_event ?? 'planifie';
        return $this;
    }

    public function getImageEvent(): ?string
    {
        return $this->image_event;
    }

    public function setImageEvent(string $image_event): self
    {
        $this->image_event = $image_event;
        return $this;
    }

    public function getTypeEvent(): ?string
    {
        return $this->type_event;
    }

    public function setTypeEvent(?string $type_event): self
    {
        $this->type_event = $type_event ?? 'reunion';
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

    public function getEditedAt(): ?\DateTimeInterface
    {
        return $this->edited_at;
    }

    public function setEditedAt(\DateTimeInterface $edited_at): self
    {
        $this->edited_at = $edited_at;
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
        $this->created_at = new \DateTime();
        $this->edited_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->edited_at = new \DateTime();
    }
}
