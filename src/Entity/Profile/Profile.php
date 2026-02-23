<?php

namespace App\Entity\Profile;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Profile\ProfileRepository::class)]
#[ORM\Table(name: 'profile')]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer', name: 'id_profile')]
    private ?int $id_profile = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['default' => null])]
    private ?int $theme = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $timezone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'description_profile')]
    private ?string $description_profile = null;

    #[ORM\Column(type: 'json', nullable: true, name: 'settings')]
    private ?array $settings = [];


    public function getIdProfile(): ?int
    {
        return $this->id_profile;
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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getTheme(): ?int
    {
        return $this->theme;
    }

    public function setTheme(?int $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTimezone(): ?int
    {
        return $this->timezone;
    }

    public function setTimezone(?int $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getDescriptionProfile(): ?string
    {
        return $this->description_profile;
    }

    public function setDescriptionProfile(?string $description_profile): self
    {
        $this->description_profile = $description_profile;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

}
