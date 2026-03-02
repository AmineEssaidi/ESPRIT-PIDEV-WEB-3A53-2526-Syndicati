<?php
namespace App\Entity\Chat;

use App\Repository\Chat\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: ConversationParticipant::class, orphanRemoval: true)]
    private $participants;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isGroup = false;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User\User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id_user', nullable: true)]
    private ?\App\Entity\User\User $creator = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, orphanRemoval: true)]
    private $messages;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * @return Collection<int, ConversationParticipant>
     */
    public function getParticipants(): Collection
    {
        if ($this->participants === null) {
            $this->participants = new ArrayCollection();
        }
        return $this->participants;
    }

    public function addParticipant(ConversationParticipant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants[] = $participant;
            $participant->setConversation($this);
        }
        return $this;
    }

    public function removeParticipant(ConversationParticipant $participant): self
    {
        if ($this->participants->removeElement($participant)) {
            // set the owning side to null (unless already changed)
            if ($participant->getConversation() === $this) {
                $participant->setConversation(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        if ($this->messages === null) {
            $this->messages = new ArrayCollection();
        }
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setConversation($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
        }
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isGroup(): bool
    {
        return $this->isGroup;
    }

    public function setIsGroup(bool $isGroup): self
    {
        $this->isGroup = $isGroup;
        return $this;
    }

    public function getCreator(): ?\App\Entity\User\User
    {
        return $this->creator;
    }

    public function setCreator(?\App\Entity\User\User $creator): self
    {
        $this->creator = $creator;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
    }
}
