<?php

namespace App\Dto;

use App\Enum\SectionType;

final readonly class ParsedChapter
{
    public function __construct(
        public string $title,
        public array $sections,
    ){

    }

}
