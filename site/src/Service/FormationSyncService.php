<?php

namespace App\Service;

use App\Dto\ParsedChapter;
use App\Dto\SyncReport;
use App\Entity\Chapter;
use App\Entity\Formation;
use App\Entity\Section;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Exception\CommonMarkException;
use Symfony\Component\Finder\Finder;

/**
 * Synchronise le contenu markdown du dépôt parent vers la base : upsert
 * idempotent par slug. Utilisé par la commande CLI et par le bouton admin.
 *
 * Invariant : seuls les champs CONTENU sont écrits. Les champs admin
 * (visibility, difficulty, tags, estimatedMinutes) et le statut éditorial ne
 * sont jamais touchés, pas plus que les données de progression utilisateur.
 */
final class FormationSyncService
{
    /**
     * Dossiers de premier niveau du dépôt qui ne sont pas des formations.
     *
     * @var list<string>
     */
    private const array EXCLUDED_DIRS = ['consignes', 'templates', 'site', 'target'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $formationsContentDir,
        private readonly FormationRepository $formationRepository,
        private readonly ChapterParser $chapterParser,
        private readonly ReadmeParser $readmeParser,
    ) {
    }

    /**
     * @throws CommonMarkException
     */
    public function sync(): SyncReport
    {
        // Un dossier de premier niveau est une formation s'il contient un README.md
        // et n'est pas dans la liste d'exclusion (consignes, templates, site, target).
        $formationDirs = (new Finder())
            ->directories()
            ->in($this->formationsContentDir)
            ->depth(0)
            ->exclude(self::EXCLUDED_DIRS)
            ->filter(static fn (\SplFileInfo $dir): bool => is_file($dir->getPathname().'/README.md'))
            ->sortByName();

        $created = 0;
        $updated = 0;
        $chaptersCount = 0;
        $warnings = [];

        foreach ($formationDirs as $dir) {
            $slug = $dir->getBasename();

            $readme = $this->readmeParser->parse((string) file_get_contents($dir->getPathname().'/README.md'), $slug);
            if (null === $readme) {
                $warnings[] = sprintf('"%s" ignorée : aucun titre H1 dans README.md.', $slug);
                continue;
            }

            $formation = $this->formationRepository->findOneBy(['slug' => $slug]);
            if (null === $formation) {
                $formation = (new Formation())->setSlug($slug);
                $this->em->persist($formation);
                ++$created;
            } else {
                ++$updated;
            }

            // Champs CONTENU uniquement. On ne touche jamais aux champs admin
            // (visibility, difficulty, tags, estimatedMinutes) ni au statut éditorial.
            // Tous les blocs du README sont déjà rendus en HTML par le ReadmeParser,
            // comme les sections de chapitre (cf. ChapterParser).
            $formation
                ->setTitle($readme->title)
                ->setDescription($readme->description)
                ->setPrerequisites($readme->prerequisites)
                ->setObjectives($readme->objectives)
                ->setProject($readme->project);

            $chaptersCount += $this->syncChapters($formation, $dir->getPathname(), $slug);
        }

        $this->em->flush();

        return new SyncReport($created, $updated, $chaptersCount, $warnings);
    }

    /**
     * Upsert des chapitres par slug (jamais de delete : ChapterProgress y est rattaché).
     */
    private function syncChapters(Formation $formation, string $dir, string $formationSlug): int
    {
        $chapterFiles = (new Finder())
            ->files()
            ->in($dir)
            ->depth(0)
            ->name('/^\d{2}-.+\.md$/') // NN-slug.md, README.md exclu
            ->sortByName();

        $count = 0;
        foreach ($chapterFiles as $file) {
            preg_match('/^(\d{2})-(.+)\.md$/', $file->getFilename(), $m);
            $position = (int) $m[1];
            $chapterSlug = $m[2];

            $parsed = $this->chapterParser->parse($file->getContents(), $formationSlug);

            $chapter = $this->findChapterBySlug($formation, $chapterSlug);
            if (null === $chapter) {
                $chapter = (new Chapter())->setSlug($chapterSlug);
                $formation->addChapter($chapter); // persistance en cascade via Formation::$chapters
            }
            $chapter
                ->setPosition($position)
                ->setTitle($parsed->title);

            $this->syncSections($chapter, $parsed);
            ++$count;
        }

        return $count;
    }

    private function findChapterBySlug(Formation $formation, string $slug): ?Chapter
    {
        foreach ($formation->getChapters() as $chapter) {
            if ($chapter->getSlug() === $slug) {
                return $chapter;
            }
        }

        return null;
    }

    /**
     * Aucune donnée utilisateur n'est rattachée aux sections : on vide et on recrée.
     * orphanRemoval sur Chapter::$sections supprime les anciennes lignes.
     */
    private function syncSections(Chapter $chapter, ParsedChapter $parsed): void
    {
        foreach ($chapter->getSections() as $section) {
            $chapter->removeSection($section);
        }

        foreach ($parsed->sections as $parsedSection) {
            $section = (new Section())
                ->setType($parsedSection->type)
                ->setTitle($parsedSection->title)
                ->setContent($parsedSection->html)
                ->setPosition($parsedSection->position);
            $chapter->addSection($section); // persistance en cascade via Chapter::$sections
        }
    }
}
