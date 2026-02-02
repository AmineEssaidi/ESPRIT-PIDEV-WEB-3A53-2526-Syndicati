<?php

namespace App\Entity;

use App\Repository\ReclamationsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReclamationsRepository::class)]
class Reclamations
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titrereclamations = null;

    #[ORM\Column(length: 255)]
    private ?string $descreclamation = null;

    #[ORM\Column]
    private ?\DateTime $datereclamation = null;

    #[ORM\Column(length: 255)]
    private ?string $statutreclamation = null;

    /**
     * @var Collection<int, Reponses>
     */
    #[ORM\OneToMany(
    mappedBy: 'reclamation',
    targetEntity: Reponses::class,
    cascade: ['remove'],
    orphanRemoval: true
)]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitrereclamations(): ?string
    {
        return $this->titrereclamations;
    }

    public function setTitrereclamations(string $titrereclamations): static
    {
        $this->titrereclamations = $titrereclamations;

        return $this;
    }

    public function getDescreclamation(): ?string
    {
        return $this->descreclamation;
    }

    public function setDescreclamation(string $descreclamation): static
    {
        $this->descreclamation = $descreclamation;

        return $this;
    }

    public function getDatereclamation(): ?\DateTime
    {
        return $this->datereclamation;
    }

    public function setDatereclamation(\DateTime $datereclamation): static
    {
        $this->datereclamation = $datereclamation;

        return $this;
    }

    public function getStatutreclamation(): ?string
    {
        return $this->statutreclamation;
    }

    public function setStatutreclamation(string $statutreclamation): static
    {
        $this->statutreclamation = $statutreclamation;

        return $this;
    }

    /**
     * @return Collection<int, Reponses>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Reponses $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setReclamation($this);
        }

        return $this;
    }

    public function removeReponse(Reponses $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getReclamation() === $this) {
                $reponse->setReclamation(null);
            }
        }

        return $this;
    }
}
