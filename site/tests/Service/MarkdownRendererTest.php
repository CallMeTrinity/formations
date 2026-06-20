<?php

namespace App\Tests\Service;

use App\Service\MarkdownRenderer;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $params = []): string => 'app_formation_show' === $name
                ? '/formations/'.$params['slug']
                : '/formations/'.$params['slug'].'/'.$params['chapterSlug']
        );
        $this->renderer = new MarkdownRenderer(
            new GithubFlavoredMarkdownConverter(['html_input' => 'allow']),
            $urlGenerator,
        );
    }

    public function testRewritesSameFormationChapterLink(): void
    {
        $html = $this->renderer->render('[Chapitre 2](02-variables.md)', 'f-linux-bash');

        self::assertStringContainsString('href="/formations/f-linux-bash/variables"', $html);
    }

    public function testRewritesReadmeLinkToFormationPage(): void
    {
        $html = $this->renderer->render('[Sommaire](README.md)', 'f-linux-bash');

        self::assertStringContainsString('href="/formations/f-linux-bash"', $html);
    }

    public function testRewritesCrossFormationLinkToFormationPage(): void
    {
        $html = $this->renderer->render('[Linux & Bash](../f-linux-bash/)', 'f-linux-avance');

        self::assertStringContainsString('href="/formations/f-linux-bash"', $html);
        self::assertStringNotContainsString('../f-linux-bash', $html);
    }

    public function testRewritesCrossFormationLinkWithoutTrailingSlash(): void
    {
        $html = $this->renderer->render('[Symfony](../f-symfony)', 'f-webhooks');

        self::assertStringContainsString('href="/formations/f-symfony"', $html);
    }

    public function testRewritesCrossFormationChapterLink(): void
    {
        $html = $this->renderer->render('[Messenger](../f-symfony-avance/03-messenger.md)', 'f-webhooks');

        self::assertStringContainsString('href="/formations/f-symfony-avance/messenger"', $html);
    }

    public function testPreservesAnchorOnRewrittenLink(): void
    {
        $html = $this->renderer->render('[Intro](../f-symfony/README.md#prerequis)', 'f-webhooks');

        self::assertStringContainsString('href="/formations/f-symfony#prerequis"', $html);
    }

    public function testLeavesExternalAndAbsoluteLinksUntouched(): void
    {
        $html = $this->renderer->render(
            '[Doc](https://symfony.com) [Accueil](/formations) [Ancre](#section)',
            'f-symfony',
        );

        self::assertStringContainsString('href="https://symfony.com"', $html);
        self::assertStringContainsString('href="/formations"', $html);
        self::assertStringContainsString('href="#section"', $html);
    }
}
