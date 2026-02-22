<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\PublicationReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationReactionRepository::class)]
#[ORM\Table(name: 'pubreaction')]
class PublicationReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id_pubreaction", type: 'integer')]
    private $id_pubreaction;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'id_publication', referencedColumnName: 'id', nullable: false)]
    private $publication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(name: "reaction_type", type: 'string', length: 10, columnDefinition: "ENUM('like', 'dislike')")]
    private $reaction_type;

    public function getIdPubreaction(): ?int
    {
        return $this->id_pubreaction;
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

    public function getReactionType(): ?string
    {
        return $this->reaction_type;
    }

    public function setReactionType(string $reaction_type): self
    {
        $this->reaction_type = $reaction_type;

        return $this;
    }
}
