<?php

namespace App\Enum;

enum Status: string
{
    case DRAFT = 'draft';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::DONE => 'Terminé',
        };
    }
}
