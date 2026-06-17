<?php

namespace App\Service;

use App\Dto\ParsedReadme;
use League\CommonMark\Exception\CommonMarkException;

use function Symfony\Component\String\u;

/**
 * Découpe le README.md d'une formation en blocs canoniques
 * (cf. consignes/structure-formation.md) : présentation, prérequis, objectifs,
 * projet fil rouge. Le « Plan de la formation » est volontairement ignoré : il
 * est déjà reconstruit en base via les entités Chapter et affiché comme tel.
 *
 * Même esprit que ChapterParser : on découpe au titre H2, sans couper dans un
 * bloc de code clôturé, et chaque bloc retenu est rendu en HTML.
 */
final class ReadmeParser
{
    public function __construct(private MarkdownRenderer $renderer)
    {
    }

    /**
     * @return ParsedReadme|null null si aucun titre H1 n'est trouvé
     *
     * @throws CommonMarkException
     */
    public function parse(string $markdown, string $formationSlug): ?ParsedReadme
    {
        $markdown = $this->stripNavigation($markdown);

        ['title' => $title, 'intro' => $intro, 'sections' => $rawSections] = $this->parseLines($markdown);

        if ('' === $title) {
            return null;
        }

        $prerequisites = null;
        $objectives = null;
        $project = null;

        foreach ($rawSections as $section) {
            $html = $this->renderBlock($section['body'], $formationSlug);
            if (null === $html) {
                continue;
            }

            match ($this->mapBlock($section['title'])) {
                'prerequisites' => $prerequisites = $html,
                'objectives' => $objectives = $html,
                'project' => $project = $html,
                default => null, // « Plan de la formation » et tout bloc hors canon : ignorés
            };
        }

        return new ParsedReadme(
            title: $title,
            description: (string) $this->renderBlock($intro, $formationSlug),
            prerequisites: $prerequisites,
            objectives: $objectives,
            project: $project,
        );
    }

    /**
     * Retire la barre de navigation de pied de README (« Commencer par le
     * [chapitre 1 →](…) »), doublon de la navigation du site, ainsi que le
     * séparateur --- resté orphelin une fois la ligne retirée. Calqué sur
     * ChapterParser::stripNavigation().
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
            if (!$inFence && preg_match('/^\s*Commencer par\b/i', $line)) {
                continue; // ligne de navigation de pied : on la jette
            }
            $kept[] = $line;
        }

        return preg_replace('/\n\s*(?:-{3,}|\*{3,}|_{3,})\s*$/', '', rtrim(implode("\n", $kept))) ?? '';
    }

    /**
     * Découpe le markdown en titre H1 + présentation (avant le premier H2) +
     * sections H2, sans couper à l'intérieur d'un bloc de code clôturé.
     *
     * @return array{title: string, intro: list<string>, sections: list<array{title: string, body: list<string>}>}
     */
    private function parseLines(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: []; // \R = any Unicode newline sequence
        $title = '';
        $intro = [];
        $sections = [];
        $current = null;
        $inFence = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence; // on entre / sort d'un bloc de code
            }
            if (!$inFence && '' === $title && preg_match('/^#\s+(.+)$/', $line, $m)) {
                $title = trim($m[1]); // titre H1 de la formation
                continue;
            }
            if (!$inFence && preg_match('/^##\s+(.+)$/', $line, $m)) {
                $sections[] = ['title' => trim($m[1]), 'body' => []];
                $current = array_key_last($sections);
                continue;
            }
            if (null === $current) {
                if ('' !== $title) {
                    $intro[] = $line; // présentation : entre le H1 et le premier H2
                }
                continue;
            }
            $sections[$current]['body'][] = $line;
        }

        return ['title' => $title, 'intro' => $intro, 'sections' => $sections];
    }

    /**
     * @param list<string> $body
     *
     * @throws CommonMarkException
     */
    private function renderBlock(array $body, string $formationSlug): ?string
    {
        $markdown = trim(implode("\n", $body));
        if ('' === $markdown) {
            return null;
        }

        return $this->renderer->render($markdown, $formationSlug);
    }

    /**
     * @return 'prerequisites'|'objectives'|'project'|'other'
     */
    private function mapBlock(string $title): string
    {
        $n = (string) u($title)->ascii()->lower(); // "Prérequis" -> "prerequis"

        return match (true) {
            str_contains($n, 'prerequis') => 'prerequisites',
            str_contains($n, 'sauras faire'), str_contains($n, 'ce que tu') => 'objectives',
            str_contains($n, 'projet') => 'project',
            default => 'other',
        };
    }
}
