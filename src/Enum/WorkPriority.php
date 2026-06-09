<?php

namespace App\Enum;

enum WorkPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Faible',
            self::MEDIUM => 'Moyen',
            self::HIGH => 'Élevé',
        };
    }
}
