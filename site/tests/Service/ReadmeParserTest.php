<?php

namespace App\Tests\Service;

use App\Service\MarkdownRenderer;
use App\Service\ReadmeParser;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PHPUnit\Framework\TestCase;

final class ReadmeParserTest extends TestCase
{
    private ReadmeParser $parser;

    protected function setUp(): void
    {
        $renderer = new MarkdownRenderer(new GithubFlavoredMarkdownConverter(['html_input' => 'allow']));
        $this->parser = new ReadmeParser($renderer);
    }

    public function testExtractsTitleAndPresentationAsDescription(): void
    {
        $parsed = $this->parser->parse($this->readme(), 'demo');

        self::assertNotNull($parsed);
        self::assertSame('Formation Démo', $parsed->title);
        self::assertSame("<p>Une présentation en deux phrases.\nElle tient sur plusieurs lignes.</p>\n", $parsed->description);
    }

    public function testMapsCanonicalBlocksToTheirFields(): void
    {
        $parsed = $this->parser->parse($this->readme(), 'demo');

        self::assertNotNull($parsed);
        self::assertSame("<p>Aucune connaissance préalable.</p>\n", $parsed->prerequisites);
        self::assertStringContainsString('<li>Faire un truc.</li>', (string) $parsed->objectives);
        self::assertStringContainsString('Construire un projet fil rouge', (string) $parsed->project);
    }

    public function testIgnoresThePlanBlock(): void
    {
        $parsed = $this->parser->parse($this->readme(), 'demo');

        self::assertNotNull($parsed);
        // Le « Plan de la formation » est reconstruit via les chapitres : il n'est porté par aucun champ.
        foreach ([$parsed->description, $parsed->prerequisites, $parsed->objectives, $parsed->project] as $html) {
            self::assertStringNotContainsString('Plan de la formation', (string) $html);
        }
    }

    public function testStripsFooterNavigation(): void
    {
        $parsed = $this->parser->parse($this->readme(), 'demo');

        self::assertNotNull($parsed);
        // La nav de pied (« Commencer par… ») et son séparateur --- orphelin sont retirés.
        self::assertStringNotContainsString('Commencer par', (string) $parsed->project);
        self::assertStringNotContainsString('<hr', (string) $parsed->project);
    }

    public function testLeavesUnknownBlocksUnmapped(): void
    {
        // Un README sans aucun bloc canonique : seuls titre et description sont remplis.
        $markdown = <<<'MD'
            # Titre seul

            Juste une présentation.
            MD;

        $parsed = $this->parser->parse($markdown, 'demo');

        self::assertNotNull($parsed);
        self::assertNull($parsed->prerequisites);
        self::assertNull($parsed->objectives);
        self::assertNull($parsed->project);
    }

    public function testReturnsNullWhenNoH1(): void
    {
        self::assertNull($this->parser->parse("Pas de titre H1.\n\n## Prérequis\n\nAucun.", 'demo'));
    }

    public function testParsesTheCanonicalTemplateWithoutError(): void
    {
        $template = (string) file_get_contents(__DIR__.'/../../../templates/formation-README.md');

        $parsed = $this->parser->parse($template, 'demo');

        self::assertNotNull($parsed);
        self::assertNotNull($parsed->prerequisites);
        self::assertNotNull($parsed->objectives);
        self::assertNotNull($parsed->project);
    }

    private function readme(): string
    {
        return <<<'MD'
            # Formation Démo

            Une présentation en deux phrases.
            Elle tient sur plusieurs lignes.

            ## Prérequis

            Aucune connaissance préalable.

            ## Ce que tu sauras faire à la fin

            - Faire un truc.
            - Faire un autre truc.

            ## Plan de la formation

            1. [Introduction](01-introduction.md)
            2. [Suite](02-suite.md)

            ## Projet fil rouge

            Construire un projet fil rouge au fil des chapitres.

            ---

            Commencer par le [chapitre 1 →](01-introduction.md).
            MD;
    }
}
