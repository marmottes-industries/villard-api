<?php

namespace App\Enum;

/**
 * Couleur d'accent d'un logement. Sert à distinguer les logements d'un coup
 * d'œil : le client teinte sa couleur primaire (boutons, focus, sélections)
 * avec celle du logement actif.
 *
 * La palette est volontairement fermée plutôt que libre en hexadécimal. Les
 * clients affichent du texte blanc sur cet accent (bouton primaire) : chaque
 * teinte est choisie assez sombre pour tenir le contraste AA (>= 4.5:1 sur
 * blanc). Un hexadécimal libre laisserait passer un accent illisible.
 *
 * Les clients n'ont pas besoin de connaître la table de correspondance :
 * `Property::getAccentHex()` expose l'hexadécimal en lecture seule.
 */
enum AccentColor: string
{
    case FOREST = 'forest';
    case LAKE = 'lake';
    case WOOD = 'wood';
    case SLATE = 'slate';
    case PLUM = 'plum';
    case LICHEN = 'lichen';

    /**
     * Teinte de base. Les variantes claire/foncée et le fond sont dérivés
     * côté client (`color-mix`), il n'y a donc qu'une valeur à porter ici.
     */
    public function getHex(): string
    {
        return match ($this) {
            self::FOREST => '#2E4A39',
            self::LAKE => '#2C5159',
            self::WOOD => '#97653A',
            self::SLATE => '#4F6076',
            self::PLUM => '#6E4B5E',
            self::LICHEN => '#5F6440',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::FOREST => 'Sapin',
            self::LAKE => 'Lac',
            self::WOOD => 'Bois',
            self::SLATE => 'Ardoise',
            self::PLUM => 'Myrtille',
            self::LICHEN => 'Lichen',
        };
    }
}
