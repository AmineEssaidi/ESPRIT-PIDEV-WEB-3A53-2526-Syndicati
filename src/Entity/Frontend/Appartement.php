<?php

namespace App\Entity\Frontend;

use App\Repository\Frontend\AppartementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppartementRepository::class)]
class Appartement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $numero = null;

    #[ORM\Column]
    private ?int $etage = null;

    #[ORM\Column(length: 5)]
    private ?string $bloc = null;

    #[ORM\Column]
    private ?bool $parking = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $image_a = null;

    #[ORM\Column(length: 10)]
    private ?string $type_a = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getEtage(): ?int
    {
        return $this->etage;
    }

    public function setEtage(int $etage): static
    {
        $this->etage = $etage;

        return $this;
    }

    public function getBloc(): ?string
    {
        return $this->bloc;
    }

    public function setBloc(string $bloc): static
    {
        $this->bloc = $bloc;

        return $this;
    }

    public function isParking(): ?bool
    {
        return $this->parking;
    }

    public function setParking(bool $parking): static
    {
        $this->parking = $parking;

        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;

        return $this;
    }

    public function getImageA(): ?string
    {
        return $this->image_a;
    }

    public function setImageA(?string $image_a): static
    {
        $this->image_a = $image_a;

        return $this;
    }

    public function getTypeA(): ?string
    {
        return $this->type_a;
    }

    public function setTypeA(string $type_a): static
    {
        $this->type_a = $type_a;

        return $this;
    }
}
