<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\InventoryItem;
use App\Entity\Note;
use App\Entity\Occupation;
use App\Entity\Property;
use App\Entity\PropertyMember;
use App\Entity\Room;
use App\Entity\ShoppingItem;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\AccentColor;
use App\Enum\PropertyRole;
use App\Enum\RoomType;
use App\Enum\State;
use App\Enum\WorkPriority;
use App\Enum\WorkStatus;
use App\Enum\WorkType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de développement, et support de validation du cloisonnement
 * multi-logements en l'absence de suite de tests.
 *
 * Deux logements aux coordonnées distinctes, avec des appartenances
 * volontairement **disjointes** : `sophie` et `pierre` ne voient que « Les
 * Tennis », `marie` et `lucas` que « Le Cabanon ». `antonin` est le seul
 * membre des deux, ce qui permet de tester la bascule ; `admin` n'est membre
 * d'aucun mais traverse tout via `ROLE_ADMIN`.
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsers($manager);
        $properties = $this->loadProperties($manager);
        $this->loadMemberships($manager, $users, $properties);

        $categories = $this->loadCategories($manager);
        $rooms = $this->loadRooms($manager, $properties);
        $this->loadInventoryItems($manager, $categories, $rooms, $properties);
        $this->loadShoppingItems($manager, $categories, $properties);
        $this->loadOccupations($manager, $users, $properties);
        $this->loadNotes($manager, $users, $properties);
        $this->loadWorks($manager, $users, $rooms, $properties);

        $manager->flush();
    }

    /**
     * @return array<string, User>
     */
    private function loadUsers(ObjectManager $manager): array
    {
        $definitions = [
            'admin'    => ['password' => 'admin',    'roles' => ['ROLE_ADMIN']],
            'antonin'  => ['password' => 'antonin',  'roles' => []],
            'sophie'   => ['password' => 'sophie',   'roles' => []],
            'pierre'   => ['password' => 'pierre',   'roles' => []],
            'marie'    => ['password' => 'marie',    'roles' => []],
            'lucas'    => ['password' => 'lucas',    'roles' => []],
        ];

        $users = [];
        foreach ($definitions as $username => $data) {
            $user = new User();
            $user->setUsername($username);
            $user->setRoles($data['roles']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $manager->persist($user);
            $users[$username] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, Property>
     */
    private function loadProperties(ObjectManager $manager): array
    {
        // « Les Tennis » reprend les coordonnées des anciennes variables
        // WEATHER_*, qui ne vivent plus que comme seed de fixtures.
        $tennis = new Property();
        $tennis->setName('Les Tennis');
        $tennis->setSlug('les-tennis');
        $tennis->setCity('Villard-de-Lans');
        $tennis->setAddress('12 rue des Clarines');
        $tennis->setLatitude(45.064757765580204);
        $tennis->setLongitude(5.548400944891808);
        $tennis->setTimezone('Europe/Paris');
        $tennis->setSecondaryLocationName('Côte 2000');
        $tennis->setSecondaryLatitude(45.0186219050606);
        $tennis->setSecondaryLongitude(5.571823469177524);
        $tennis->setAccentColor(AccentColor::FOREST);
        $manager->persist($tennis);

        // Coordonnées franchement éloignées : deux appels météo successifs sur
        // les deux logements doivent renvoyer des prévisions différentes.
        // Accent distinct : la bascule de logement doit se voir à l'écran.
        $cabanon = new Property();
        $cabanon->setName('Le Cabanon');
        $cabanon->setSlug('le-cabanon');
        $cabanon->setCity('Collioure');
        $cabanon->setAddress("4 impasse de l'Anse");
        $cabanon->setLatitude(42.52673);
        $cabanon->setLongitude(3.08307);
        $cabanon->setTimezone('Europe/Paris');
        $cabanon->setAccentColor(AccentColor::LAKE);
        $manager->persist($cabanon);

        return ['les-tennis' => $tennis, 'le-cabanon' => $cabanon];
    }

    /**
     * @param array<string, User>     $users
     * @param array<string, Property> $properties
     */
    private function loadMemberships(ObjectManager $manager, array $users, array $properties): void
    {
        $memberships = [
            ['antonin', 'les-tennis', PropertyRole::MANAGER],
            ['sophie',  'les-tennis', PropertyRole::OCCUPANT],
            ['pierre',  'les-tennis', PropertyRole::OCCUPANT],
            ['antonin', 'le-cabanon', PropertyRole::OCCUPANT],
            ['marie',   'le-cabanon', PropertyRole::MANAGER],
            ['lucas',   'le-cabanon', PropertyRole::OCCUPANT],
        ];

        foreach ($memberships as [$username, $slug, $role]) {
            $member = new PropertyMember();
            $member->setUser($users[$username]);
            $member->setProperty($properties[$slug]);
            $member->setRole($role);
            $manager->persist($member);
        }
    }

    /**
     * Les catégories restent communes à tous les logements (choix assumé).
     *
     * @return array<string, Category>
     */
    private function loadCategories(ObjectManager $manager): array
    {
        $names = ['Cuisine', 'Salle de bain', 'Chambre', 'Salon', 'Extérieur', 'Cave', 'Produits frais', 'Épicerie'];

        $categories = [];
        foreach ($names as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        return $categories;
    }

    /**
     * Les pièces reproduisent exactement ce que la reprise SQL de
     * {@see \DoctrineMigrations\Version20260812091248} produit sur ces mêmes
     * fixtures : c'est le seul moyen de valider la migration sans base de
     * production.
     *
     * @param array<string, Property> $properties
     *
     * @return array<string, array<string, Room>> slug du logement → nom de pièce → pièce
     */
    private function loadRooms(ObjectManager $manager, array $properties): array
    {
        $definitions = [
            'les-tennis' => ['Cuisine', 'Salon', 'Chambre', 'Salle de bain', 'Cave', 'Extérieur'],
            'le-cabanon' => ['Cuisine', 'Chambre', 'Salle de bain', 'Cave', 'Extérieur'],
        ];

        $types = [
            'Cuisine' => RoomType::KITCHEN,
            'Salon' => RoomType::LIVING_ROOM,
            'Chambre' => RoomType::BEDROOM,
            'Salle de bain' => RoomType::BATHROOM,
            'Cave' => RoomType::CELLAR,
            'Extérieur' => RoomType::OUTDOOR,
        ];

        $rooms = [];
        foreach ($definitions as $slug => $names) {
            foreach ($names as $position => $name) {
                $room = new Room();
                $room->setName($name);
                $room->setType($types[$name]);
                $room->setPosition($position);
                $room->setProperty($properties[$slug]);
                $manager->persist($room);
                $rooms[$slug][$name] = $room;
            }
        }

        return $rooms;
    }

    /**
     * Chaque article reçoit sa pièce **et** conserve sa catégorie : c'est
     * l'état exact de la base après migration en production, tant que les
     * clients déjà installés continuent d'écrire `category`. Le paramètre
     * `$categories` et l'appel à `setCategory()` disparaîtront avec la colonne,
     * à la prochaine majeure.
     *
     * @param array<string, Category>              $categories
     * @param array<string, array<string, Room>>   $rooms
     * @param array<string, Property>              $properties
     */
    private function loadInventoryItems(ObjectManager $manager, array $categories, array $rooms, array $properties): void
    {
        $items = [
            'les-tennis' => [
                'Cuisine'       => [['Assiettes plates', 12], ['Verres à eau', 8], ['Couteaux', 10], ['Casseroles', 4], ['Cafetière', 1]],
                'Salle de bain' => [['Serviettes de bain', 10], ['Gants de toilette', 12], ['Tapis de bain', 2]],
                'Chambre'       => [['Draps housse 140', 4], ['Housses de couette', 4], ['Oreillers', 6], ['Couvertures', 3]],
                'Salon'         => [['Plaids', 3], ['Coussins', 6], ['Jeux de société', 5]],
                'Extérieur'     => [['Chaises de jardin', 6], ['Parasol', 1], ['Barbecue', 1]],
                'Cave'          => [['Skis adulte', 4], ['Skis enfant', 2], ['Luge', 3], ['Vélos', 2]],
            ],
            'le-cabanon' => [
                'Cuisine'       => [['Assiettes creuses', 8], ['Verres à pied', 6], ['Poêle', 2]],
                'Salle de bain' => [['Serviettes de plage', 8], ['Sèche-cheveux', 1]],
                'Chambre'       => [['Draps housse 160', 2], ['Oreillers', 4]],
                'Extérieur'     => [['Transats', 4], ['Parasol', 2], ['Douche de jardin', 1]],
                'Cave'          => [['Masques et tubas', 4], ['Paddle', 1], ['Glacière', 2]],
            ],
        ];

        foreach ($items as $slug => $byRoom) {
            foreach ($byRoom as $roomName => $list) {
                foreach ($list as [$name, $quantity]) {
                    $item = new InventoryItem();
                    $item->setName($name);
                    $item->setQuantity($quantity);
                    $item->setRoom($rooms[$slug][$roomName]);
                    $item->setCategory($categories[$roomName]);
                    $item->setState(State::OK);
                    $item->setProperty($properties[$slug]);
                    $manager->persist($item);
                }
            }
        }
    }

    /**
     * @param array<string, Category> $categories
     * @param array<string, Property> $properties
     */
    private function loadShoppingItems(ObjectManager $manager, array $categories, array $properties): void
    {
        $items = [
            ['Lait', 6, false, 'Produits frais', 'les-tennis'],
            ['Pain', 2, true, 'Produits frais', 'les-tennis'],
            ['Beurre', 1, false, 'Produits frais', 'les-tennis'],
            ['Pâtes', 4, false, 'Épicerie', 'les-tennis'],
            ['Café', 1, true, 'Épicerie', 'les-tennis'],
            ['Sel', 1, false, 'Épicerie', 'les-tennis'],
            ['Liquide vaisselle', 2, false, 'Cuisine', 'les-tennis'],
            ['Éponges', 3, false, 'Cuisine', 'les-tennis'],
            ['Papier toilette', 12, false, 'Salle de bain', 'les-tennis'],
            ['Savon', 4, true, 'Salle de bain', 'les-tennis'],
            ['Crème solaire', 3, false, 'Salle de bain', 'le-cabanon'],
            ['Charbon de bois', 2, false, 'Extérieur', 'le-cabanon'],
            ['Riz', 3, false, 'Épicerie', 'le-cabanon'],
            ['Huile d\'olive', 1, true, 'Épicerie', 'le-cabanon'],
            ['Anchois', 4, false, 'Produits frais', 'le-cabanon'],
        ];

        foreach ($items as [$name, $quantity, $purchased, $categoryName, $slug]) {
            $item = new ShoppingItem();
            $item->setName($name);
            $item->setQuantity($quantity);
            $item->setPurchased($purchased);
            $item->setCategory($categories[$categoryName]);
            $item->setProperty($properties[$slug]);
            $manager->persist($item);
        }
    }

    /**
     * @param array<string, User>     $users
     * @param array<string, Property> $properties
     */
    private function loadOccupations(ObjectManager $manager, array $users, array $properties): void
    {
        // Toutes les dates sont en juin 2026 (mois en cours)
        $occupations = [
            ['antonin', 'les-tennis', '2026-06-01', '2026-06-05', 'Long week-end en famille'],
            ['sophie',  'les-tennis', '2026-06-06', '2026-06-09', 'Visite avec les enfants'],
            ['pierre',  'les-tennis', '2026-06-10', '2026-06-14', null],
            ['antonin', 'les-tennis', '2026-06-22', '2026-06-25', 'Télétravail au calme'],
            ['sophie',  'les-tennis', '2026-06-26', '2026-06-30', 'Fin de mois en famille'],
            ['marie',   'le-cabanon', '2026-06-13', '2026-06-16', 'Week-end entre amies'],
            ['lucas',   'le-cabanon', '2026-06-17', '2026-06-21', 'Stage de plongée'],
            ['antonin', 'le-cabanon', '2026-06-27', '2026-06-30', 'Fin juin au bord de l\'eau'],
        ];

        foreach ($occupations as [$username, $slug, $start, $end, $notes]) {
            $occupation = new Occupation();
            $occupation->setOccupant($users[$username]);
            $occupation->setProperty($properties[$slug]);
            $occupation->setStartDate(new \DateTimeImmutable($start));
            $occupation->setEndDate(new \DateTimeImmutable($end));
            $occupation->setNotes($notes);
            $manager->persist($occupation);
        }
    }

    /**
     * @param array<string, User>     $users
     * @param array<string, Property> $properties
     */
    private function loadNotes(ObjectManager $manager, array $users, array $properties): void
    {
        $notes = [
            ['antonin', 'les-tennis', '2026-06-02 10:15:00', 'Chaudière',         "Pensez à purger les radiateurs avant de partir, la chaudière fait du bruit sinon."],
            ['sophie',  'les-tennis', '2026-06-08 18:42:00', 'Code Wifi',         "Le nouveau code Wifi est noté sur le frigo (post-it bleu)."],
            ['pierre',  'les-tennis', '2026-06-12 09:00:00', 'Voisins du dessus', "Les voisins du dessus refont leur sol jusqu'au 20 juin, prévoir des bouchons d'oreille."],
            ['antonin', 'les-tennis', '2026-06-23 08:00:00', 'Poubelles',         "Sortie des poubelles : jaunes le mardi soir, ménagères le jeudi soir."],
            ['marie',   'le-cabanon', '2026-06-15 21:30:00', 'Boulangerie',       "La boulangerie du port est fermée le mardi, prévoir le pain à l'avance."],
            ['lucas',   'le-cabanon', '2026-06-19 14:00:00', 'Local à bateaux',   "La clé du local à bateaux est dans le tiroir de l'entrée."],
            ['antonin', 'le-cabanon', '2026-06-28 09:30:00', 'Volets',            "Fermer les volets côté mer avant de partir, la tramontane les abîme."],
        ];

        foreach ($notes as [$username, $slug, $createdAt, $title, $content]) {
            $note = new Note();
            $note->setAuthor($users[$username]);
            $note->setProperty($properties[$slug]);
            $note->setTitle($title);
            $note->setContent($content);
            $note->setCreatedAt(new \DateTimeImmutable($createdAt));
            $manager->persist($note);
        }
    }

    /**
     * @param array<string, User>                $users
     * @param array<string, array<string, Room>> $rooms
     * @param array<string, Property>            $properties
     */
    private function loadWorks(ObjectManager $manager, array $users, array $rooms, array $properties): void
    {
        // Le dernier travail reste sans pièce : les deux branches doivent être
        // exercées côté clients.
        $works = [
            ['antonin', 'les-tennis', 'Salle de bain', 'Remplacer le ballon d\'eau chaude',       WorkStatus::PLANNED,     WorkType::PRO, WorkPriority::HIGH,   1200],
            ['sophie',  'les-tennis', 'Chambre',       'Repeindre la chambre du fond',            WorkStatus::SUGGESTED,   WorkType::DIY, WorkPriority::LOW,     300],
            ['pierre',  'les-tennis', 'Cave',          'Réviser la chaudière',                    WorkStatus::DONE,        WorkType::PRO, WorkPriority::MEDIUM,  180],
            ['marie',   'le-cabanon', 'Extérieur',     'Reprendre l\'étanchéité de la terrasse',  WorkStatus::IN_PROGRESS, WorkType::PRO, WorkPriority::HIGH,   2500],
            ['lucas',   'le-cabanon', null,            'Installer une douche extérieure',         WorkStatus::SUGGESTED,   WorkType::DIY, WorkPriority::LOW,     450],
        ];

        foreach ($works as [$username, $slug, $roomName, $title, $status, $type, $priority, $cost]) {
            $work = new Work();
            $work->setAuthor($users[$username]);
            $work->setProperty($properties[$slug]);
            $work->setRoom(null === $roomName ? null : $rooms[$slug][$roomName]);
            $work->setTitle($title);
            $work->setStatus($status);
            $work->setType($type);
            $work->setPriority($priority);
            $work->setEstimatedCost($cost);
            $work->setCreatedAt(new \DateTimeImmutable('2026-06-01 09:00:00'));
            $manager->persist($work);
        }
    }
}
