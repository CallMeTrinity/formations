<?php

namespace App\Tests\Service;

use App\Dto\ParsedSection;
use App\Enum\SectionType;
use App\Service\ChapterParser;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;

final class ChapterParserTest extends TestCase
{
    private ChapterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ChapterParser(new CommonMarkConverter(['html_input' => 'allow']));
    }

    public function testExtractsTheH1Title(): void
    {
        $parsed = $this->parser->parse($this->template(), 'symfony');

        self::assertSame('{{Titre du chapitre}}', $parsed->title);
    }

    public function testIgnoresNavigationAndKeepsOnlyH2Sections(): void
    {
        $parsed = $this->parser->parse($this->template(), 'symfony');

        // 7 titres ## ; la barre de navigation (avant le premier ##) est ignorée.
        self::assertCount(7, $parsed->sections);
    }

    public function testMapsHeadingsToSectionTypes(): void
    {
        $parsed = $this->parser->parse($this->template(), 'symfony');

        self::assertSame(SectionType::OBJECTIVES, $parsed->sections[0]->type);
        self::assertSame(SectionType::SUMMARY, $this->section($parsed->sections, SectionType::SUMMARY)->type);
        self::assertSame(SectionType::EXERCISES, $this->section($parsed->sections, SectionType::EXERCISES)->type);
        self::assertSame(SectionType::QUIZ, $this->section($parsed->sections, SectionType::QUIZ)->type);
        self::assertSame(SectionType::PROJECT, $this->section($parsed->sections, SectionType::PROJECT)->type);
        // Un titre libre (non reconnu) retombe sur CONTENT.
        self::assertSame(SectionType::CONTENT, $parsed->sections[1]->type);
    }

    public function testRendersMarkdownBodyToHtml(): void
    {
        $parsed = $this->parser->parse($this->template(), 'symfony');

        self::assertStringContainsString(
            '<p>À la fin de ce chapitre, tu sauras :</p>',
            $parsed->sections[0]->html,
        );
    }

    public function testPreservesInlineHtmlSuchAsDetails(): void
    {
        $parsed = $this->parser->parse($this->template(), 'symfony');

        $exercices = $this->section($parsed->sections, SectionType::EXERCISES);

        self::assertStringContainsString('<details>', $exercices->html);
    }

    public function testRewritesInterChapterLinks(): void
    {
        $markdown = <<<'MD'
            # Titre

            ## Contenu

            Voir le [chapitre suivant](02-anatomie-projet.md) puis le [sommaire](README.md#plan).
            Et un lien externe : [Symfony](https://symfony.com).
            MD;

        $parsed = $this->parser->parse($markdown, 'symfony');
        $html = $parsed->sections[0]->html;

        // NN-slug.md -> /formations/{slug}/{chapitre}
        self::assertStringContainsString('href="/formations/symfony/anatomie-projet"', $html);
        // README.md#ancre -> /formations/{slug}#ancre
        self::assertStringContainsString('href="/formations/symfony#plan"', $html);
        // Les liens externes ne bougent pas.
        self::assertStringContainsString('href="https://symfony.com"', $html);
    }

    /**
     * @param list<ParsedSection> $sections
     */
    private function section(array $sections, SectionType $type): ParsedSection
    {
        foreach ($sections as $section) {
            if ($section->type === $type) {
                return $section;
            }
        }

        self::fail(sprintf('Aucune section de type %s trouvée.', $type->value));
    }

    private function template(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../templates/chapitre.md');
    }
}
