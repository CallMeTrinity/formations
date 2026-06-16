<?php

namespace App\Enum;

enum Visibility: string
{
    case DRAFT = 'draft';
    case BETA = 'beta';
    case PUBLIC = 'public';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::BETA => 'Beta',
            self::PUBLIC => 'Public',
        };
    }
}
