<?php

namespace App\Enum;


enum State: string {
    case OK = 'ok';
    case WORN = 'worn';
    case REPLACE = 'replace';

    public function getLabel(): string {
        return match($this) {
            self::OK => 'Bon état',
            self::WORN => 'Abimé',
            self::REPLACE => 'A remplacer',
        };
    }
}
