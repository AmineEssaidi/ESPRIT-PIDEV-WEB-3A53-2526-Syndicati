<?php

namespace App\Entity\Residence;

use App\Repository\Residence\AppartementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\User;

#[ORM\Entity(repositoryClass: AppartementRepository::class)]
#[ORM\Table(name: 'appartement')]
class Appartement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer', name: 'id_app')]
    private ?int $idApp = null;

    #[ORM\ManyToOne(targetEntity: Residence::class, inversedBy: 'appartements')]
    #[ORM\JoinColumn(name: 'residence_id', referencedColumnName: 'id_residence', nullable: false, onDelete: 'CASCADE')]
    private ?Residence $residence = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $parking = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $disponible = null;

    #[ORM\Column(type: 'string', length: 500, name: 'image_a', nullable: true)]
    private ?string $imageA = null;

    #[ORM\Column(type: 'string', length: 255, name: 'type_a', columnDefinition: "ENUM('STUDIO', 'S+1', 'S+2', 'S+3', 'S+4', 'S+5')")]
    private ?string $typeA = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $appartementInfo = [];

    #[ORM\Column(nullable: true)]
    private ?float $superficie = null;

    #[ORM\Column(nullable: true)]
    private ?float $prix_location = null;

    #[ORM\Column(nullable: true)]
    private ?float $prix_vente = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_construction = null;

    #[ORM\OneToOne(mappedBy: 'appartement', targetEntity: Maintenance::class, cascade: ['persist', 'remove'])]
    private ?Maintenance $maintenance = null;

    public function getIdApp(): ?int
    {
        return $this->idApp;
    }

    public function getResidence(): ?Residence
    {
        return $this->residence;
    }

    public function setResidence(?Residence $residence): self
    {
        $this->residence = $residence;

        return $this;
    }

    public function isParking(): ?bool
    {
        return $this->parking;
    }

    public function setParking(bool $parking): self
    {
        $this->parking = $parking;

        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;

        return $this;
    }

    public function getImageA(): ?string
    {
        return $this->imageA;
    }

    public function setImageA(?string $imageA): self
    {
        $this->imageA = $imageA;

        return $this;
    }

    public function getTypeA(): ?string
    {
        return $this->typeA;
    }

    public function setTypeA(string $typeA): self
    {
        $normalized = strtoupper(trim($typeA));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        if ($normalized === 'STUDIO') {
            $this->typeA = 'STUDIO';
            return $this;
        }

        if (preg_match('/^S\+?([1-5])$/', $normalized, $matches)) {
            $this->typeA = 'S+' . $matches[1];
            return $this;
        }

        $this->typeA = $typeA;

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

    public function getAppartementInfo(): ?array
    {
        return $this->appartementInfo;
    }

    public function setAppartementInfo(?array $appartementInfo): self
    {
        $this->appartementInfo = $appartementInfo;
        return $this;
    }

    public function getSuperficie(): ?float
    {
        return $this->superficie;
    }

    public function setSuperficie(?float $superficie): static
    {
        $this->superficie = $superficie;

        return $this;
    }

    public function getPrixLocation(): ?float
    {
        return $this->prix_location;
    }

    public function setPrixLocation(?float $prix_location): static
    {
        $this->prix_location = $prix_location;

        return $this;
    }

    public function getPrixVente(): ?float
    {
        return $this->prix_vente;
    }

    public function setPrixVente(?float $prix_vente): static
    {
        $this->prix_vente = $prix_vente;

        return $this;
    }

    public function getDateConstruction(): ?\DateTime
    {
        return $this->date_construction;
    }

    public function setDateConstruction(?\DateTime $date_construction): static
    {
        $this->date_construction = $date_construction;

        return $this;
    }

    public function getMaintenance(): ?Maintenance
    {
        return $this->maintenance;
    }

    public function setMaintenance(Maintenance $maintenance): static
    {
        if ($maintenance->getAppartement() !== $this) {
            $maintenance->setAppartement($this);
        }

        $this->maintenance = $maintenance;

        return $this;
    }
}
