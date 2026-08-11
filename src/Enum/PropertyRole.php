<?php

namespace App\Enum;

/**
 * Rôle local d'un utilisateur dans un logement donné. Indépendant des rôles
 * Symfony globaux : `ROLE_ADMIN` reste un super-rôle qui traverse tous les
 * logements, cf. {@see \App\Security\Voter\PropertyVoter}.
 */
enum PropertyRole: string
{
    case MANAGER = 'manager';
    case OCCUPANT = 'occupant';

    public function getLabel(): string
    {
        return match ($this) {
            self::MANAGER => 'Gestionnaire',
            self::OCCUPANT => 'Occupant',
        };
    }
}
