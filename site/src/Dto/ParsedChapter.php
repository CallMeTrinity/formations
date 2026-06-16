<?php

namespace App\Dto;

final readonly class ParsedChapter
{
    /**
     * @param list<ParsedSection> $sections
     */
    public function __construct(
        public string $title,
        public array $sections,
    ) {
    }
}
