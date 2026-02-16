<?php
namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;

#[ORM\Entity(repositoryClass: \App\Repository\User\UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email_user'], message: 'This email is already registered.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface
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

    #[ORM\Column(name: 'authCode', type: 'string', length: 50, nullable: true)]
    private $authCode;

    #[ORM\Column(name: 'authCode_expires_at', type: 'datetime', nullable: true)]
    private $authCode_expires_at;

    #[ORM\Column(name: 'two_factor_enabled', type: 'boolean', options: ['default' => false])]
    private $twoFactorEnabled = false;

    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    private $totpSecret;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    #[ORM\Column(type: 'datetime')]
    private $updated_at;

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

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function setAuthCode(?string $authCode): self
    {
        $this->authCode = $authCode;
        return $this;
    }

    public function getAuthCodeExpiresAt(): ?\DateTimeInterface
    {
        return $this->authCode_expires_at;
    }

    public function setAuthCodeExpiresAt(?\DateTimeInterface $authCode_expires_at): self
    {
        $this->authCode_expires_at = $authCode_expires_at;
        return $this;
    }

    /**
     * True if user has a non-expired auth code (for 2FA / email verification).
     */
    public function isAuthCodeValid(): bool
    {
        if ($this->authCode === null || $this->authCode === '' || $this->authCode_expires_at === null) {
            return false;
        }
        return $this->authCode_expires_at > new \DateTime();
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

    // Two-Factor Authentication Methods
    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): self
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    // TotpTwoFactorInterface methods
    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled && $this->totpSecret !== null;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email_user;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (!$this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
