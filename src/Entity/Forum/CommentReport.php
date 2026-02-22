<?php

namespace App\Entity\Forum;

use App\Entity\User\User;
use App\Repository\Forum\CommentReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentReportRepository::class)]
#[ORM\Table(name: 'comment_report')]
class CommentReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id_commentreport", type: 'integer')]
    private $id_commentreport;

    #[ORM\ManyToOne(targetEntity: Commentaire::class)]
    #[ORM\JoinColumn(name: 'id_commentaire', referencedColumnName: 'id_commentaire', nullable: false, onDelete: 'CASCADE')]
    private $comment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private $user;

    #[ORM\Column(name: "`signal`" , type: 'boolean')]
    private $signal;

    public function getIdCommentreport(): ?int
    {
        return $this->id_commentreport;
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
