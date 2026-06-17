<?php

namespace App\Dto;

/**
 * README de formation découpé : titre, présentation et les blocs canoniques
 * (cf. consignes/structure-formation.md). Le « Plan de la formation » n'est pas
 * porté ici : il est reconstruit en base via les entités Chapter.
 *
 * Tous les champs textuels sont déjà rendus en HTML.
 */
final readonly class ParsedReadme
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $prerequisites = null,
        public ?string $objectives = null,
        public ?string $project = null,
    ) {
    }
}
