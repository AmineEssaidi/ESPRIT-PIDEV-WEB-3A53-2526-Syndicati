<?php

namespace App\Entity\Syndicat;

use App\Entity\User\User;
use App\Repository\Syndicat\ReclamationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamations')]
#[ORM\HasLifecycleCallbacks]
class Reclamation
{
    public const STATUTS = ['active', 'en_attente', 'refuse', 'termine'];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'idreclamations')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, name: 'titrereclamations')]
    #[Assert\NotBlank(message: "The subject cannot be blank.")]
    #[Assert\Length(min: 5, minMessage: "The subject must be at least 5 characters long.")]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\s.,!?'\"-]*$/",
        message: "The subject can only contain letters, numbers, spaces, and common punctuation."
    )]
    private $titrereclamations;

    #[ORM\Column(type: 'string', length: 255, name: 'descreclamation')]
    #[Assert\NotBlank(message: "The description cannot be blank.")]
    #[Assert\Length(min: 10, minMessage: "The description must be at least 10 characters long.")]
    private $descreclamation;

    #[ORM\Column(type: 'datetime', name: 'datereclamation')]
    private $datereclamation;

    #[ORM\Column(type: 'string', length: 20, name: 'statutreclamation', columnDefinition: "ENUM('active', 'en_attente', 'refuse', 'termine') DEFAULT 'en_attente'")]
    #[Assert\Choice(choices: self::STATUTS)]
    private $statutreclamation = 'en_attente';

    #[ORM\Column(type: 'string', length: 255, name: 'imagereclamation', nullable: true)]
    private $imagereclamation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\OneToMany(mappedBy: 'reclamation', targetEntity: Reponse::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $reponses;

    public function __construct()
    {
        $this->reponses = new \Doctrine\Common\Collections\ArrayCollection();
    }

    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private $created_at;

    #[ORM\Column(type: 'datetime', name: 'updated_at')]
    private $updated_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitrereclamations(): ?string
    {
        return $this->titrereclamations;
    }

    public function setTitrereclamations(?string $v): self
    {
        $this->titrereclamations = $v;
        return $this;
    }

    public function getDescreclamation(): ?string
    {
        return $this->descreclamation;
    }

    public function setDescreclamation(?string $v): self
    {
        $this->descreclamation = $v;
        return $this;
    }

    public function getDatereclamation(): ?\DateTimeInterface
    {
        return $this->datereclamation;
    }

    public function setDatereclamation(?\DateTimeInterface $v): self
    {
        $this->datereclamation = $v;
        return $this;
    }

    public function getStatutreclamation(): ?string
    {
        return $this->statutreclamation;
    }

    public function setStatutreclamation(?string $v): self
    {
        $this->statutreclamation = $v;
        return $this;
    }

    public function getImagereclamation(): ?string
    {
        return $this->imagereclamation;
    }

    public function setImagereclamation(?string $v): self
    {
        $this->imagereclamation = $v;
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

    public function setCreatedAt(\DateTimeInterface $v): self
    {
        $this->created_at = $v;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $v): self
    {
        $this->updated_at = $v;
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

    public function getReponses(): \Doctrine\Common\Collections\Collection
    {
        if ($this->reponses === null) {
            $this->reponses = new \Doctrine\Common\Collections\ArrayCollection();
        }
        return $this->reponses;
    }
}
