<?php

namespace App\Enum;

enum Difficulty: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::BEGINNER => 'Débutant',
            self::INTERMEDIATE => 'Intermédiaire',
            self::ADVANCED => 'Avancé',
        };
    }
}
