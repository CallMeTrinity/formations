<?php

namespace App\Service;

use App\Dto\ParsedChapter;
use App\Dto\ParsedSection;
use App\Enum\SectionType;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

use function Symfony\Component\String\u;

final class ChapterParser
{
    public function __construct(private CommonMarkConverter $converter)
    {
    }

    /**
     * @throws CommonMarkException
     */
    public function parse(string $markdown, string $formationSlug): ParsedChapter
    {
        $markdown = $this->stripNavigation($markdown);

        ['title' => $title, 'sections' => $rawSections] = $this->parseLines($markdown);

        $sections = [];
        foreach ($rawSections as $index => $section) {
            $bodyMarkdown = trim(implode("\n", $section['body']));
            $html = $this->converter->convert($bodyMarkdown)->getContent();
            $html = $this->rewriteInterChapterLinks($html, $formationSlug);

            $sections[] = new ParsedSection(
                type: $this->mapType($section['title']),
                title: $section['title'],
                html: $html,
                position: $index + 1,
            );
        }

        return new ParsedChapter(title: $title, sections: $sections);
    }

    /**
     * Retire les barres de navigation du markdown source (en tête et pied de
     * chapitre) : ce sont des doublons de la navigation du lecteur (template),
     * et leurs liens pointent vers des fichiers .md. On reconnaît une ligne de
     * nav à son lien « [Sommaire](README.md) ». Un séparateur --- resté orphelin
     * en bas de chapitre après ce retrait est lui aussi supprimé.
     */
    private function stripNavigation(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $inFence = false;
        $kept = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
            }
            if (!$inFence && str_contains($line, '[Sommaire](README.md)')) {
                continue; // ligne de navigation : on la jette
            }
            $kept[] = $line;
        }

        // Séparateur de bas de chapitre devenu orphelin une fois la nav retirée.
        return preg_replace('/\n\s*(?:-{3,}|\*{3,}|_{3,})\s*$/', '', rtrim(implode("\n", $kept))) ?? '';
    }

    /**
     * Découpe le markdown en titre H1 + sections H2, sans couper à l'intérieur
     * d'un bloc de code clôturé.
     *
     * @return array{title: string, sections: list<array{title: string, body: list<string>}>}
     */
    private function parseLines(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: []; // \R = any Unicode newline sequence
        $title = '';
        $sections = [];
        $current = null;
        $inFence = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence; // on entre / sort d'un bloc de code
            }
            if (!$inFence && '' === $title && preg_match('/^#\s+(.+)$/', $line, $m)) {
                $title = trim($m[1]); // titre H1 du chapitre
                continue;
            }
            if (!$inFence && preg_match('/^##\s+(.+)$/', $line, $m)) {
                $sections[] = ['title' => trim($m[1]), 'body' => []];
                $current = array_key_last($sections);
                continue;
            }
            if (null !== $current) {
                $sections[$current]['body'][] = $line;
            }
        }

        return ['title' => $title, 'sections' => $sections];
    }

    private function mapType(string $title): SectionType
    {
        $n = (string) u($title)->ascii()->lower(); // "Résumé" -> "resume"

        return match (true) {
            str_contains($n, 'objectif') => SectionType::OBJECTIVES,
            str_contains($n, 'resume') => SectionType::SUMMARY,
            str_contains($n, 'exercice') => SectionType::EXERCISES,
            str_contains($n, 'quiz') => SectionType::QUIZ,
            str_contains($n, 'projet') => SectionType::PROJECT,
            default => SectionType::CONTENT,
        };
    }

    /**
     * Réécrit les liens relatifs inter-chapitres (NN-slug.md, README.md) vers
     * les chemins du lecteur. Laisse intacts les liens externes, absolus et les ancres.
     *
     * TODO #1 : passer par UrlGeneratorInterface quand la route du lecteur existera.
     */
    private function rewriteInterChapterLinks(string $html, string $formationSlug): string
    {
        return preg_replace_callback('/href="([^"]+)"/', function (array $m) use ($formationSlug): string {
            $url = $m[1];
            if (str_starts_with($url, 'http') || str_starts_with($url, 'mailto:')
                || str_starts_with($url, '/') || str_starts_with($url, '#')) {
                return $m[0]; // externe | absolu | ancre : inchangé
            }

            [$path, $anchor] = array_pad(explode('#', $url, 2), 2, null);
            if (!preg_match('/^(\d{2}-[a-z0-9-]+|README)\.md$/', (string) $path, $mm)) {
                return $m[0]; // pas un lien inter-chapitre reconnu
            }

            $target = 'README' === $mm[1]
                ? '/formations/'.$formationSlug
                : '/formations/'.$formationSlug.'/'.preg_replace('/^\d{2}-/', '', $mm[1]);

            return 'href="'.$target.(null !== $anchor ? '#'.$anchor : '').'"';
        }, $html) ?? $html;
    }
}
