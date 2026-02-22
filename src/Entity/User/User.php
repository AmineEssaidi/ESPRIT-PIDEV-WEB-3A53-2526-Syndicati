<?php
namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: \App\Repository\User\UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email_user'], message: 'This email is already registered.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Allowed roles for user (ENUM-like)
     */
    public const ROLES = ['RESIDENT', 'SYNDIC', 'OWNER', 'ADMIN', 'SUPERADMIN'];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer', name: 'id_user')]
    private $id_user;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'The first name cannot contain numbers or special characters.'
    )]
    private $first_name;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'The last name cannot contain numbers or special characters.'
    )]
    private $last_name;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private $email_user;

    #[ORM\Column(type: 'string', length: 255)]
    private $password_user;

    /**
     * @Assert\NotBlank(groups={"registration"})
     * @Assert\Length(min=8, groups={"registration"})
     * @Assert\Regex(
     *     pattern="/[A-Z]/",
     *     message="Password must contain at least one uppercase letter.",
     *     groups={"registration"}
     * )
     * @Assert\Regex(
     *     pattern="/[!@#$%^&*(),.?\"\":{}|<>]/",
     *     message="Password must contain at least one special character.",
     *     groups={"registration"}
     * )
     */
    private $plainPassword;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'RESIDENT'], columnDefinition: "ENUM('RESIDENT', 'SYNDIC', 'OWNER', 'ADMIN', 'SUPERADMIN') DEFAULT 'RESIDENT'")]
    private $role_user = 'RESIDENT';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $is_verified = false;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime')]
    private $updated_at;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+?[0-9]{8,15}$/',
        message: 'Please enter a valid phone number (e.g., +21612345678).'
    )]
    private $telephone_user;

    // Getters and setters...
    public function getIdUser(): ?int
    {
        return $this->id_user;
    }
    public function getFirstName(): ?string
    {
        return $this->first_name;
    }
    public function setFirstName(string $first_name): self
    {
        $this->first_name = $first_name;
        return $this;
    }
    public function getLastName(): ?string
    {
        return $this->last_name;
    }
    public function setLastName(string $last_name): self
    {
        $this->last_name = $last_name;
        return $this;
    }
    public function getEmailUser(): ?string
    {
        return $this->email_user;
    }
    public function setEmailUser(string $email_user): self
    {
        $this->email_user = $email_user;
        return $this;
    }
    public function getPasswordUser(): ?string
    {
        return $this->password_user;
    }
    public function setPasswordUser(string $password_user): self
    {
        $this->password_user = $password_user;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }
    public function getRoleUser(): ?string
    {
        return $this->role_user;
    }
    public function setRoleUser(string $role_user): self
    {
        $this->role_user = $role_user;
        return $this;
    }
    public function getIsVerified(): ?bool
    {
        return $this->is_verified;
    }
    public function setIsVerified(bool $is_verified): self
    {
        $this->is_verified = $is_verified;
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
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }
    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getTelephoneUser(): ?string
    {
        return $this->telephone_user;
    }

    public function setTelephoneUser(?string $telephone_user): self
    {
        $this->telephone_user = $telephone_user;
        return $this;
    }

    /**
     * Set created_at and updated_at automatically
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    /**
     * Update updated_at automatically
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }

    // UserInterface Methods
    public function getRoles(): array
    {
        return [$this->role_user];
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email_user;
    }

    // PasswordAuthenticatedUserInterface Methods
    public function getPassword(): ?string
    {
        return $this->password_user;
    }
}
