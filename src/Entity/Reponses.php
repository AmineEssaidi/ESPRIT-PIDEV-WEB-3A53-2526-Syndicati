<?php

namespace App\Entity;

use App\Repository\ReponsesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReponsesRepository::class)]
class Reponses
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $messagereponse = null;

    #[ORM\Column]
    private ?\DateTime $datereponse = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reclamations $reclamation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessagereponse(): ?string
    {
        return $this->messagereponse;
    }

    public function setMessagereponse(string $messagereponse): static
    {
        $this->messagereponse = $messagereponse;

        return $this;
    }

    public function getDatereponse(): ?\DateTime
    {
        return $this->datereponse;
    }

    public function setDatereponse(\DateTime $datereponse): static
    {
        $this->datereponse = $datereponse;

        return $this;
    }

    public function getReclamation(): ?reclamations
    {
        return $this->reclamation;
    }

    public function setReclamation(?reclamations $reclamation): static
    {
        $this->reclamation = $reclamation;

        return $this;
    }
}
