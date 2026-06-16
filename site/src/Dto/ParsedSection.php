<?php

namespace App\Dto;

use App\Enum\SectionType;

final readonly class ParsedSection
{
    public function __construct(
        public SectionType $type,
        public string $title,
        public string $html,
        public int $position,
    ) {
    }
}
