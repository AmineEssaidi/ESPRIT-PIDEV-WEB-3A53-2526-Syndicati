<?php

namespace App\Entity\Backend;

use App\Repository\Backend\ResidenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResidenceRepository::class)]
class Residence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $nom_r = null;

    #[ORM\Column(length: 100)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_r = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_ajout = null;

    #[ORM\Column]
    private ?int $n_appartements = null;

    #[ORM\Column]
    private ?int $n_etages = null;

    #[ORM\Column]
    private ?int $n_blocs = null;

    /**
     * @var Collection<int, Appartement>
     */
    #[ORM\OneToMany(targetEntity: Appartement::class, mappedBy: 'residence', orphanRemoval: true)]
    private Collection $appartements;

    public function __construct()
    {
        $this->appartements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomR(): ?string
    {
        return $this->nom_r;
    }

    public function setNomR(string $nom_r): static
    {
        $this->nom_r = $nom_r;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getImageR(): ?string
    {
        return $this->image_r;
    }

    public function setImageR(?string $image_r): static
    {
        $this->image_r = $image_r;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->date_ajout;
    }

    public function setDateAjout(\DateTime $date_ajout): static
    {
        $this->date_ajout = $date_ajout;

        return $this;
    }

    public function getNAppartements(): ?int
    {
        return $this->n_appartements;
    }

    public function setNAppartements(int $n_appartements): static
    {
        $this->n_appartements = $n_appartements;

        return $this;
    }

    public function getNEtages(): ?int
    {
        return $this->n_etages;
    }

    public function setNEtages(int $n_etages): static
    {
        $this->n_etages = $n_etages;

        return $this;
    }

    public function getNBlocs(): ?int
    {
        return $this->n_blocs;
    }

    public function setNBlocs(int $n_blocs): static
    {
        $this->n_blocs = $n_blocs;

        return $this;
    }

    /**
     * @return Collection<int, Appartement>
     */
    public function getAppartements(): Collection
    {
        return $this->appartements;
    }

    public function addAppartement(Appartement $appartement): static
    {
        if (!$this->appartements->contains($appartement)) {
            $this->appartements->add($appartement);
            $appartement->setResidence($this);
        }

        return $this;
    }

    public function removeAppartement(Appartement $appartement): static
    {
        if ($this->appartements->removeElement($appartement)) {
            // set the owning side to null (unless already changed)
            if ($appartement->getResidence() === $this) {
                $appartement->setResidence(null);
            }
        }

        return $this;
    }
}