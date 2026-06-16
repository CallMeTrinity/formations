<?php

namespace App\Entity;

use App\Enum\Difficulty;
use App\Repository\UserPreferencesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferencesRepository::class)]
class UserPreferences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'preferences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'userPreferences')]
    private Collection $preferredTags;

    #[ORM\Column(nullable: true, enumType: Difficulty::class)]
    private ?Difficulty $preferredDifficulty = null;

    #[ORM\Column(nullable: true)]
    private ?int $weeklyGoalMinutes = null;

    public function __construct()
    {
        $this->preferredTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getPreferredTags(): Collection
    {
        return $this->preferredTags;
    }

    public function addPreferredTag(Tag $preferredTag): static
    {
        if (!$this->preferredTags->contains($preferredTag)) {
            $this->preferredTags->add($preferredTag);
        }

        return $this;
    }

    public function removePreferredTag(Tag $preferredTag): static
    {
        $this->preferredTags->removeElement($preferredTag);

        return $this;
    }

    public function getPreferredDifficulty(): ?Difficulty
    {
        return $this->preferredDifficulty;
    }

    public function setPreferredDifficulty(?Difficulty $preferredDifficulty): static
    {
        $this->preferredDifficulty = $preferredDifficulty;

        return $this;
    }

    public function getWeeklyGoalMinutes(): ?int
    {
        return $this->weeklyGoalMinutes;
    }

    public function setWeeklyGoalMinutes(?int $weeklyGoalMinutes): static
    {
        $this->weeklyGoalMinutes = $weeklyGoalMinutes;

        return $this;
    }
}
