<?php

namespace App\Service;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Rendu markdown → HTML commun au contenu pédagogique (chapitres et README) :
 * conversion CommonMark + réécriture des liens relatifs inter-chapitres vers
 * les routes du lecteur.
 */
final class MarkdownRenderer
{
    public function __construct(
        private GithubFlavoredMarkdownConverter $converter,
        private UrlGeneratorInterface $urlGenerator,
    ) {
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
     * Réécrit les liens relatifs du contenu pédagogique vers les routes du lecteur :
     * - inter-chapitres d'une même formation : NN-slug.md, README.md
     * - inter-formations : ../autre-formation/[README.md|NN-slug.md]
     *
     * Laisse intacts les liens externes, absolus et les ancres.
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

            // Lien inter-formation : ../<slug>/ éventuellement suivi d'un chapitre.
            if (preg_match('#^\.\./([a-z0-9-]+)/?(.*)$#', (string) $path, $cm)) {
                $target = $this->resolveTarget($cm[1], $cm[2]);

                return null !== $target ? 'href="'.$target.$this->suffix($anchor).'"' : $m[0];
            }

            // Lien inter-chapitre dans la formation courante : NN-slug.md | README.md.
            if (!preg_match('/^(\d{2}-[a-z0-9-]+|README)\.md$/', (string) $path, $mm)) {
                return $m[0]; // pas un lien interne reconnu
            }

            $target = $this->resolveTarget($formationSlug, $mm[1].'.md');

            return null !== $target ? 'href="'.$target.$this->suffix($anchor).'"' : $m[0];
        }, $html) ?? $html;
    }

    /**
     * Génère l'URL du lecteur pour une formation et un fichier cible
     * (vide ou README.md → page formation ; NN-slug.md → chapitre).
     * Retourne null si le fichier n'est pas une cible reconnue.
     */
    private function resolveTarget(string $formationSlug, string $file): ?string
    {
        if ('' === $file || 'README.md' === $file) {
            return $this->urlGenerator->generate('app_formation_show', ['slug' => $formationSlug]);
        }

        if (preg_match('/^\d{2}-([a-z0-9-]+)\.md$/', $file, $fm)) {
            return $this->urlGenerator->generate('app_formation_chapter', [
                'slug' => $formationSlug,
                'chapterSlug' => $fm[1],
            ]);
        }

        return null;
    }

    private function suffix(?string $anchor): string
    {
        return null !== $anchor ? '#'.$anchor : '';
    }
}
