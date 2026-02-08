<?php

namespace App\Entity\Onboarding;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Onboarding\OnboardingRepository::class)]
#[ORM\Table(name: 'onboarding')]
class Onboarding
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer', name: 'id_onboarding')]
    private ?int $id_onboarding = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $step = 1;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $completed = false;

    #[ORM\Column(type: 'string', length: 255)]
    private string $selected_locale = 'fr';

    #[ORM\Column(type: 'string', length: 255)]
    private string $selected_theme = 'dark';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $selected_preferences = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $suggestions = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $started_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', onDelete: 'CASCADE')]
    private ?User $user = null;

    public function getIdOnboarding(): ?int
    {
        return $this->id_onboarding;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function setStep(int $step): self
    {
        $this->step = $step;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): self
    {
        $this->completed = $completed;
        return $this;
    }

    public function getSelectedLocale(): string
    {
        return $this->selected_locale;
    }

    public function setSelectedLocale(string $selected_locale): self
    {
        $this->selected_locale = $selected_locale;
        return $this;
    }

    public function getSelectedTheme(): string
    {
        return $this->selected_theme;
    }

    public function setSelectedTheme(string $selected_theme): self
    {
        $this->selected_theme = $selected_theme;
        return $this;
    }

    public function getSelectedPreferences(): ?array
    {
        return $this->selected_preferences;
    }

    public function setSelectedPreferences(?array $selected_preferences): self
    {
        $this->selected_preferences = $selected_preferences;
        return $this;
    }

    public function getSuggestions(): ?string
    {
        return $this->suggestions;
    }

    public function setSuggestions(?string $suggestions): self
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->started_at;
    }

    public function setStartedAt(?\DateTimeInterface $started_at): self
    {
        $this->started_at = $started_at;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completed_at;
    }

    public function setCompletedAt(?\DateTimeInterface $completed_at): self
    {
        $this->completed_at = $completed_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
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
}
