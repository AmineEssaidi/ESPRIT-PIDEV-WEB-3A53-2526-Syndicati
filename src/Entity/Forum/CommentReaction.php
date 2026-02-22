<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\CommentReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentReactionRepository::class)]
#[ORM\Table(name: 'commentreaction')]
class CommentReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id_commentreaction", type: 'integer')]
    private $id_commentreaction;

    #[ORM\ManyToOne(targetEntity: Commentaire::class)]
    #[ORM\JoinColumn(name: 'id_commentaire', referencedColumnName: 'id_commentaire', nullable: false, onDelete: 'CASCADE')]
    private $comment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(name: "reaction_type", type: 'string', length: 10, columnDefinition: "ENUM('like', 'dislike')")]
    private $reaction_type;

    public function getIdCommentreaction(): ?int
    {
        return $this->id_commentreaction;
    }

    public function getComment(): ?Commentaire
    {
        return $this->comment;
    }

    public function setComment(?Commentaire $comment): self
    {
        $this->comment = $comment;

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
