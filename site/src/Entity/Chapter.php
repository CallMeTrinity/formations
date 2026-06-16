<?php

namespace App\Entity;

use App\Repository\ChapterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CHAPTER_FORMATION_SLUG', fields: ['formation', 'slug'])]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'chapters')]
    private ?Formation $formation = null;

    // Prefix NN- from file NN-slug.md
    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /**
     * @var Collection<int, Section>
     */
    #[ORM\OneToMany(targetEntity: Section::class, mappedBy: 'chapter')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sections;

    /**
     * @var Collection<int, ChapterProgress>
     */
    #[ORM\OneToMany(targetEntity: ChapterProgress::class, mappedBy: 'chapter')]
    private Collection $chapterProgress;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
        $this->chapterProgress = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Section>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(Section $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setChapter($this);
        }

        return $this;
    }

    public function removeSection(Section $section): static
    {
        if ($this->sections->removeElement($section)) {
            // set the owning side to null (unless already changed)
            if ($section->getChapter() === $this) {
                $section->setChapter(null);
            }
        }

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
            $chapterProgress->setChapter($this);
        }

        return $this;
    }

    public function removeChapterProgress(ChapterProgress $chapterProgress): static
    {
        if ($this->chapterProgress->removeElement($chapterProgress)) {
            // set the owning side to null (unless already changed)
            if ($chapterProgress->getChapter() === $this) {
                $chapterProgress->setChapter(null);
            }
        }

        return $this;
    }
}
