<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ENROLLMENT_USER_FORMATION', fields: ['user_id', 'formation_id'])]
class Enrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    private ?Formation $formation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @var Collection<int, ChapterProgress>
     */
    #[ORM\OneToMany(targetEntity: ChapterProgress::class, mappedBy: 'enrollment')]
    private Collection $chapterProgress;

    public function __construct()
    {
        $this->chapterProgress = new ArrayCollection();
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    /**
     * @return Collection<int, ChapterProgress>
     */
    public function getChapterProgress(): Collection
    {
        return $this->chapterProgress;
    }

    public function addChapterProgress(ChapterProgress $chapterProgress): static
    {
        if (!$this->chapterProgress->contains($chapterProgress)) {
            $this->chapterProgress->add($chapterProgress);
            $chapterProgress->setEnrollment($this);
        }

        return $this;
    }

    public function removeChapterProgress(ChapterProgress $chapterProgress): static
    {
        if ($this->chapterProgress->removeElement($chapterProgress)) {
            // set the owning side to null (unless already changed)
            if ($chapterProgress->getEnrollment() === $this) {
                $chapterProgress->setEnrollment(null);
            }
        }

        return $this;
    }
}
