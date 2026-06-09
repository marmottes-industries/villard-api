<?php

namespace App\Enum;

enum WorkStatus: string
{
    case SUGGESTED = 'suggested';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUGGESTED => 'Suggestion',
            self::PLANNED => 'Planifié',
            self::IN_PROGRESS => 'En cours',
            self::DONE => 'Fait',
            self::CANCELLED => 'Abandonné',
        };
    }
}
