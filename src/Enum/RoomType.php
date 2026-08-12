<?php

namespace App\Enum;

/**
 * Nature d'une pièce. Purement indicatif : c'est {@see \App\Entity\Room::$name}
 * qui identifie la pièce (« Chambre 1 », « Cabane à skis »), le type ne sert
 * qu'à dériver une icône côté client et, plus tard, à d'éventuels regroupements.
 *
 * Volontairement sans case « autre » : le champ est nullable, et une pièce
 * atypique doit rester sans type plutôt que d'en porter un faux. Même parti
 * pris que {@see WorkType} et {@see WorkPriority}, déjà nullables et rendues en
 * chips à bascule dans les deux clients.
 *
 * Aucune méthode `getIcon()` ici : contrairement à
 * {@see AccentColor::getHex()} — une couleur de marque est une donnée partagée
 * — le choix d'une icône appartient à chaque client, et le web et le mobile
 * n'ont pas le même jeu disponible.
 */
enum RoomType: string
{
    case KITCHEN = 'kitchen';
    case BATHROOM = 'bathroom';
    case TOILET = 'toilet';
    case BEDROOM = 'bedroom';
    case LIVING_ROOM = 'living_room';
    case OFFICE = 'office';
    case LAUNDRY = 'laundry';
    case HALLWAY = 'hallway';
    case GARAGE = 'garage';
    case CELLAR = 'cellar';
    case ATTIC = 'attic';
    case OUTDOOR = 'outdoor';

    public function getLabel(): string
    {
        return match ($this) {
            self::KITCHEN => 'Cuisine',
            self::BATHROOM => 'Salle de bain',
            self::TOILET => 'WC',
            self::BEDROOM => 'Chambre',
            self::LIVING_ROOM => 'Salon',
            self::OFFICE => 'Bureau',
            self::LAUNDRY => 'Buanderie',
            self::HALLWAY => 'Couloir',
            self::GARAGE => 'Garage',
            self::CELLAR => 'Cave',
            self::ATTIC => 'Combles',
            self::OUTDOOR => 'Extérieur',
        };
    }
}
