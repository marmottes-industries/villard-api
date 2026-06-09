<?php

namespace App\Enum;

enum WorkType: string
{
    case DIY = 'diy';
    case PRO = 'pro';

    public function getLabel(): string
    {
        return match ($this) {
            self::DIY => 'À faire soi-même',
            self::PRO => 'À faire faire',
        };
    }
}
