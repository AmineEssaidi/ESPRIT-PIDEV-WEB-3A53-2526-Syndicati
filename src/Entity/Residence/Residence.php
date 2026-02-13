<?php

namespace App\Entity\Residence;

use App\Repository\Residence\ResidenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ResidenceRepository::class)]
#[ORM\Table(name: 'residence')]
class Residence
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'id_residence')]
    private ?int $idResidence = null;

    #[ORM\Column(type: 'string', length: 40, name: 'nom_r')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    private ?string $nomR = null;

    #[ORM\Column(type: 'string', length: 100, name: 'adresse')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 255, name: 'image_r', nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageR = null;

    #[ORM\Column(type: 'datetime', name: 'date_ajout')]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\Column(type: 'string', length: 255, name: 'n_appartements', columnDefinition: "ENUM('1', '2', '3', '4', '5', '6', '7', '8', '9', '10')")]
    #[Assert\NotBlank]
    private ?string $nAppartements = null;

    #[ORM\Column(type: 'string', length: 255, name: 'n_etages', columnDefinition: "ENUM('0', '1', '2', '3', '4', '5')")]
    #[Assert\NotBlank]
    private ?string $nEtages = null;

    #[ORM\Column(type: 'string', length: 255, name: 'n_blocs', columnDefinition: "SET('A', 'B', 'C', 'D', 'E')")]
    #[Assert\NotBlank]
    private ?string $nBlocs = null;

    #[ORM\OneToMany(mappedBy: 'residence', targetEntity: Appartement::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $appartements;

    public function __construct()
    {
        $this->appartements = new ArrayCollection();
        $this->dateAjout = new \DateTime();
    }

    public function getIdResidence(): ?int
    {
        return $this->idResidence;
    }

    public function getNomR(): ?string
    {
        return $this->nomR;
    }

    public function setNomR(string $nomR): self
    {
        $this->nomR = $nomR;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getImageR(): ?string
    {
        return $this->imageR;
    }

    public function setImageR(?string $imageR): self
    {
        $this->imageR = $imageR;
        return $this;
    }

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeInterface $dateAjout): self
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    public function getNAppartements(): ?string
    {
        return $this->nAppartements;
    }

    public function setNAppartements(string $nAppartements): self
    {
        $this->nAppartements = $nAppartements;
        return $this;
    }

    public function getNEtages(): ?string
    {
        return $this->nEtages;
    }

    public function setNEtages(string $nEtages): self
    {
        $this->nEtages = $nEtages;
        return $this;
    }

    public function getNBlocs(): ?string
    {
        return $this->nBlocs;
    }

    public function setNBlocs(string $nBlocs): self
    {
        $this->nBlocs = $nBlocs;
        return $this;
    }

    /**
     * Get blocs as an array
     */
    public function getBlocsArray(): array
    {
        return $this->nBlocs ? explode(',', $this->nBlocs) : [];
    }

    /**
     * Set blocs from an array
     */
    public function setBlocsArray(array $blocs): self
    {
        $this->nBlocs = implode(',', $blocs);
        return $this;
    }

    /**
     * Get total apartments (apartments per floor × number of floors × number of blocs)
     */


    /**
     * @return Collection<int, Appartement>
     */
    public function getAppartements(): Collection
    {
        return $this->appartements;
    }

        public function getTotalAppartements(): int
    {
        return $totalAppartments = $this->getAppartements()->count();
    }
    public function addAppartement(Appartement $appartement): self
    {
        if (!$this->appartements->contains($appartement)) {
            $this->appartements[] = $appartement;
            $appartement->setResidence($this);
        }

        return $this;
    }

    public function removeAppartement(Appartement $appartement): self
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
