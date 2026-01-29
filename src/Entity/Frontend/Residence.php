<?php

namespace App\Entity\Frontend;

use App\Repository\Frontend\ResidenceRepository;
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
}
