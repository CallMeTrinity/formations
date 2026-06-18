<?php

namespace App\Dto;

/**
 * Compte-rendu d'une synchronisation markdown → base : ce qui a été importé et
 * les éventuels avertissements (formations ignorées, etc.).
 */
final readonly class SyncReport
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $chaptersCount,
        public array $warnings = [],
    ) {
    }
}
