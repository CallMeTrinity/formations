<?php

namespace App\Command;

use App\Dto\ParsedChapter;
use App\Entity\Chapter;
use App\Entity\Formation;
use App\Entity\Section;
use App\Repository\FormationRepository;
use App\Service\ChapterParser;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:formations:sync',
    description: 'Importe le contenu markdown du dépôt parent en base (upsert idempotent par slug).',
)]
final class FormationsSyncCommand extends Command
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
        private readonly CommonMarkConverter $converter,
    ) {
        parent::__construct();
    }

    /**
     * @throws CommonMarkException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        foreach ($formationDirs as $dir) {
            $slug = $dir->getBasename();

            $readme = $this->parseReadme((string) file_get_contents($dir->getPathname().'/README.md'));
            if (null === $readme) {
                $io->warning(sprintf('"%s" ignorée : aucun titre H1 dans README.md.', $slug));
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
            // La description (markdown inline) est convertie en HTML, comme les
            // sections de chapitre (cf. ChapterParser).
            $formation
                ->setTitle($readme['title'])
                ->setDescription($this->converter->convert($readme['description'])->getContent());

            $chaptersCount += $this->syncChapters($formation, $dir->getPathname(), $slug);
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d formation(s) créée(s), %d mise(s) à jour, %d chapitre(s) synchronisé(s).',
            $created,
            $updated,
            $chaptersCount,
        ));

        return Command::SUCCESS;
    }

    /**
     * Extrait le titre (H1) et la description (premier paragraphe) d'un README de formation.
     *
     * @return array{title: string, description: string}|null null si aucun H1 n'est trouvé
     */
    private function parseReadme(string $content): ?array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $title = '';
        $descriptionLines = [];
        $inDescription = false;

        foreach ($lines as $line) {
            if ('' === $title) {
                if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                    $title = trim($m[1]);
                }
                continue; // rien n'est collecté avant le H1
            }

            // Une ligne vide après le début du paragraphe le clôt.
            if ($inDescription && '' === trim($line)) {
                break;
            }
            // Un nouveau titre (## …) avant tout texte : pas de description.
            if (preg_match('/^#{1,6}\s+/', $line)) {
                break;
            }
            if ('' !== trim($line)) {
                $descriptionLines[] = trim($line);
                $inDescription = true;
            }
        }

        if ('' === $title) {
            return null;
        }

        return ['title' => $title, 'description' => trim(implode(' ', $descriptionLines))];
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
