<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ENROLLMENT_USER_FORMATION', fields: ['user', 'formation'])]
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
     * Date de la toute première complétion. Renseignée une seule fois et jamais
     * effacée (même après un « recommencer ») : c'est la trace d'historique.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstCompletedAt = null;

    /**
     * Nombre de fois où la formation a été menée à son terme. Incrémenté à chaque
     * complétion (y compris après un « recommencer ») : c'est le compteur affiché
     * sous forme d'étoiles. Jamais remis à zéro.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $completionCount = 0;

    /**
     * @var Collection<int, ChapterProgress>
     */
    #[ORM\OneToMany(targetEntity: ChapterProgress::class, mappedBy: 'enrollment', cascade: ['remove'], orphanRemoval: true)]
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

    public function getFirstCompletedAt(): ?\DateTimeImmutable
    {
        return $this->firstCompletedAt;
    }

    public function setFirstCompletedAt(?\DateTimeImmutable $firstCompletedAt): static
    {
        $this->firstCompletedAt = $firstCompletedAt;

        return $this;
    }

    public function getCompletionCount(): int
    {
        return $this->completionCount;
    }

    public function setCompletionCount(int $completionCount): static
    {
        $this->completionCount = $completionCount;

        return $this;
    }

    public function incrementCompletionCount(): static
    {
        ++$this->completionCount;

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
