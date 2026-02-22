<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\PublicationBookmarkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationBookmarkRepository::class)]
#[ORM\Table(name: 'publication_bookmark')]
class PublicationBookmark
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id_bookmark", type: 'integer')]
    private $id_bookmark;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'id_publication', referencedColumnName: 'id', nullable: false)]
    private $publication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(type: 'boolean')]
    private $bookmark;

    public function getIdBookmark(): ?int
    {
        return $this->id_bookmark;
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

    public function isBookmark(): ?bool
    {
        return $this->bookmark;
    }

    public function setBookmark(bool $bookmark): self
    {
        $this->bookmark = $bookmark;

        return $this;
    }
}
