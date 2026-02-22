<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\PublicationReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationReportRepository::class)]
#[ORM\Table(name: 'publication_report')]
class PublicationReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id_report", type: 'integer')]
    private $id_report;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'id_publication', referencedColumnName: 'id', nullable: false)]
    private $publication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(name: "`signal`" , type: 'boolean')]
    private $signal;

    public function getIdReport(): ?int
    {
        return $this->id_report;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;

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

    public function isSignal(): ?bool
    {
        return $this->signal;
    }

    public function setSignal(bool $signal): self
    {
        $this->signal = $signal;

        return $this;
    }
}
