<?php

namespace App\Entity\Evenement;

use App\Entity\User\User;
use App\Repository\Evenement\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\HasLifecycleCallbacks]
class Participation
{
    public const STATUTS = ['confirme', 'en_attente', 'refuse', 'annule'];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'id_participation')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Evenement::class)]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id_event', nullable: false, onDelete: 'CASCADE')]
    private $evenement;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    private $date_participation;

    #[ORM\Column(type: 'string', length: 20, columnDefinition: "ENUM('confirme', 'en_attente', 'refuse', 'annule')")]
    #[Assert\Choice(choices: self::STATUTS)]
    private $statut_participation = 'en_attente';

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private $nb_accompagnants = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $commentaire_participation;

    #[ORM\Column(type: 'json')]
    private $formulaire_data = [];

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime')]
    private $edited_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): self
    {
        $this->evenement = $evenement;
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

    public function getDateParticipation(): ?\DateTimeInterface
    {
        return $this->date_participation;
    }

    public function setDateParticipation(\DateTimeInterface $date_participation): self
    {
        $this->date_participation = $date_participation;
        return $this;
    }

    public function getStatutParticipation(): ?string
    {
        return $this->statut_participation;
    }

    public function setStatutParticipation(string $statut_participation): self
    {
        $this->statut_participation = $statut_participation;
        return $this;
    }

    public function getNbAccompagnants(): ?int
    {
        return $this->nb_accompagnants;
    }

    public function setNbAccompagnants(int $nb_accompagnants): self
    {
        $this->nb_accompagnants = $nb_accompagnants;
        return $this;
    }

    public function getCommentaireParticipation(): ?string
    {
        return $this->commentaire_participation;
    }

    public function setCommentaireParticipation(string $commentaire_participation): self
    {
        $this->commentaire_participation = $commentaire_participation;
        return $this;
    }

    public function getFormulaireData(): ?array
    {
        return $this->formulaire_data;
    }

    public function setFormulaireData(array $formulaire_data): self
    {
        $this->formulaire_data = $formulaire_data;
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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->edited_at = new \DateTime();
        if (!$this->date_participation) {
            $this->date_participation = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->edited_at = new \DateTime();
    }
}
