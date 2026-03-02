<?php
namespace App\Entity\Chat;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'message_attachment')]
#[ORM\HasLifecycleCallbacks]
class MessageAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Chat\Message', inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Message $message = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploader_id', referencedColumnName: 'id_user', nullable: false)]
    private ?User $uploader = null;

    #[ORM\Column(type: 'string', length: 500)]
    private ?string $file_path = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $original_name = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $mime_type = null;

    #[ORM\Column(type: 'bigint')]
    private ?string $size_bytes = null;

    #[ORM\Column(type: 'string', length: 10, columnDefinition: "ENUM('IMAGE','FILE','VIDEO','AUDIO')")]
    private ?string $kind = 'FILE';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getUploader(): ?User
    {
        return $this->uploader;
    }

    public function setUploader(?User $uploader): self
    {
        $this->uploader = $uploader;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->file_path;
    }

    public function setFilePath(string $file_path): self
    {
        $this->file_path = $file_path;
        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->original_name;
    }

    public function setOriginalName(string $original_name): self
    {
        $this->original_name = $original_name;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mime_type;
    }

    public function setMimeType(string $mime_type): self
    {
        $this->mime_type = $mime_type;
        return $this;
    }

    public function getSizeBytes(): ?string
    {
        return $this->size_bytes;
    }

    public function setSizeBytes(string $size_bytes): self
    {
        $this->size_bytes = $size_bytes;
        return $this;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;
        return $this;
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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
    }
}
