<?php

namespace App\Service;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

/**
 * Rendu markdown → HTML commun au contenu pédagogique (chapitres et README) :
 * conversion CommonMark + réécriture des liens relatifs inter-chapitres vers
 * les routes du lecteur.
 */
final class MarkdownRenderer
{
    public function __construct(private CommonMarkConverter $converter)
    {
    }

    /**
     * @throws CommonMarkException
     */
    public function render(string $markdown, string $formationSlug): string
    {
        $html = $this->converter->convert($markdown)->getContent();

        return $this->rewriteInterChapterLinks($html, $formationSlug);
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
