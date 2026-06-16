<?php

namespace App\Enum;

enum SectionType: string
{
    case OBJECTIVES = 'objectives';
    case SUMMARY = 'summary';
    case EXERCISES = 'exercises';
    case QUIZ = 'quiz';
    case PROJECT = 'project';
    case CONTENT = 'content';

    public function getLabel(): string
    {
        return match ($this) {
            self::OBJECTIVES => 'Objectifs',
            self::SUMMARY => 'Résumé',
            self::EXERCISES => 'Exercices',
            self::QUIZ => 'Quiz',
            self::PROJECT => 'Projet',
            self::CONTENT => 'Contenu',
        };
    }
}
