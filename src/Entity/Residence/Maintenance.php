<?php
namespace App\Entity\Residence;

use App\Repository\Residence\MaintenanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceRepository::class)]
class Maintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', name: 'id_maintenance')]  // ← just one #[ORM\Column]
    private ?int $idMaintenance = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $etat_app = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $etat_plomberie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $etat_electricite = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $etat_chauffage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_derniere_maintenance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description_maint = null;

    #[ORM\OneToOne(inversedBy: 'maintenance', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'id_app', referencedColumnName: 'id_app', nullable: false)]
    private ?Appartement $appartement = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $recommendation_ia = null;

    public function getIdMaintenance(): ?int
    {
        return $this->idMaintenance;
    }

    public function getEtatApp(): ?string
    {
        return $this->etat_app;
    }

    public function setEtatApp(?string $etat_app): static
    {
        $this->etat_app = $etat_app;
        return $this;
    }

    public function getEtatPlomberie(): ?string
    {
        return $this->etat_plomberie;
    }

    public function setEtatPlomberie(?string $etat_plomberie): static
    {
        $this->etat_plomberie = $etat_plomberie;
        return $this;
    }

    public function getEtatElectricite(): ?string
    {
        return $this->etat_electricite;
    }

    public function setEtatElectricite(?string $etat_electricite): static
    {
        $this->etat_electricite = $etat_electricite;
        return $this;
    }

    public function getEtatChauffage(): ?string
    {
        return $this->etat_chauffage;
    }

    public function setEtatChauffage(?string $etat_chauffage): static
    {
        $this->etat_chauffage = $etat_chauffage;
        return $this;
    }

    public function getDateDerniereMaintenance(): ?\DateTime
    {
        return $this->date_derniere_maintenance;
    }

    public function setDateDerniereMaintenance(?\DateTime $date_derniere_maintenance): static
    {
        $this->date_derniere_maintenance = $date_derniere_maintenance;
        return $this;
    }

    public function getDescriptionMaint(): ?string
    {
        return $this->description_maint;
    }

    public function setDescriptionMaint(?string $description_maint): static
    {
        $this->description_maint = $description_maint;
        return $this;
    }

    // Fixed: was referencing non-existent $idApp
    public function getAppartement(): ?Appartement
    {
        return $this->appartement;
    }

    public function setAppartement(Appartement $appartement): static
    {
        $this->appartement = $appartement;
        return $this;
    }

    public function getRecommendationIa(): ?string
    {
        return $this->recommendation_ia;
    }

    public function setRecommendationIa(?string $recommendation_ia): static
    {
        $this->recommendation_ia = $recommendation_ia;

        return $this;
    }
}