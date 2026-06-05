<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\InventoryItem;
use App\Entity\Note;
use App\Entity\Occupation;
use App\Entity\ShoppingItem;
use App\Entity\User;
use App\Enum\State;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsers($manager);
        $categories = $this->loadCategories($manager);
        $this->loadInventoryItems($manager, $categories);
        $this->loadShoppingItems($manager, $categories);
        $this->loadOccupations($manager, $users);
        $this->loadNotes($manager, $users);

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
     * @param array<string, Category> $categories
     */
    private function loadInventoryItems(ObjectManager $manager, array $categories): void
    {
        $items = [
            'Cuisine'       => [['Assiettes plates', 12], ['Verres à eau', 8], ['Couteaux', 10], ['Casseroles', 4], ['Cafetière', 1]],
            'Salle de bain' => [['Serviettes de bain', 10], ['Gants de toilette', 12], ['Tapis de bain', 2]],
            'Chambre'       => [['Draps housse 140', 4], ['Housses de couette', 4], ['Oreillers', 6], ['Couvertures', 3]],
            'Salon'         => [['Plaids', 3], ['Coussins', 6], ['Jeux de société', 5]],
            'Extérieur'     => [['Chaises de jardin', 6], ['Parasol', 1], ['Barbecue', 1]],
            'Cave'          => [['Skis adulte', 4], ['Skis enfant', 2], ['Luge', 3], ['Vélos', 2]],
        ];

        foreach ($items as $categoryName => $list) {
            foreach ($list as [$name, $quantity]) {
                $item = new InventoryItem();
                $item->setName($name);
                $item->setQuantity($quantity);
                $item->setCategory($categories[$categoryName]);
                $item->setState(State::OK);
                $manager->persist($item);
            }
        }
    }

    /**
     * @param array<string, Category> $categories
     */
    private function loadShoppingItems(ObjectManager $manager, array $categories): void
    {
        $items = [
            ['Lait', 6, false, 'Produits frais'],
            ['Pain', 2, true, 'Produits frais'],
            ['Beurre', 1, false, 'Produits frais'],
            ['Pâtes', 4, false, 'Épicerie'],
            ['Café', 1, true, 'Épicerie'],
            ['Sel', 1, false, 'Épicerie'],
            ['Liquide vaisselle', 2, false, 'Cuisine'],
            ['Éponges', 3, false, 'Cuisine'],
            ['Papier toilette', 12, false, 'Salle de bain'],
            ['Savon', 4, true, 'Salle de bain'],
        ];

        foreach ($items as [$name, $quantity, $purchased, $categoryName]) {
            $item = new ShoppingItem();
            $item->setName($name);
            $item->setQuantity($quantity);
            $item->setPurchased($purchased);
            $item->setCategory($categories[$categoryName]);
            $manager->persist($item);
        }
    }

    /**
     * @param array<string, User> $users
     */
    private function loadOccupations(ObjectManager $manager, array $users): void
    {
        // Toutes les dates sont en juin 2026 (mois en cours)
        $occupations = [
            ['antonin', '2026-06-01', '2026-06-05', 'Long week-end en famille'],
            ['sophie',  '2026-06-06', '2026-06-09', 'Visite avec les enfants'],
            ['pierre',  '2026-06-10', '2026-06-14', null],
            ['marie',   '2026-06-13', '2026-06-16', 'Week-end entre amies'],
            ['lucas',   '2026-06-17', '2026-06-21', 'Stage de VTT'],
            ['antonin', '2026-06-22', '2026-06-25', 'Télétravail au calme'],
            ['sophie',  '2026-06-26', '2026-06-30', 'Fin de mois en famille'],
        ];

        foreach ($occupations as [$username, $start, $end, $notes]) {
            $occupation = new Occupation();
            $occupation->setOccupant($users[$username]);
            $occupation->setStartDate(new \DateTimeImmutable($start));
            $occupation->setEndDate(new \DateTimeImmutable($end));
            $occupation->setNotes($notes);
            $manager->persist($occupation);
        }
    }

    /**
     * @param array<string, User> $users
     */
    private function loadNotes(ObjectManager $manager, array $users): void
    {
        $notes = [
            ['antonin', '2026-06-02 10:15:00', 'Chaudière',          "Pensez à purger les radiateurs avant de partir, la chaudière fait du bruit sinon."],
            ['sophie',  '2026-06-08 18:42:00', 'Code Wifi',          "Le nouveau code Wifi est noté sur le frigo (post-it bleu)."],
            ['pierre',  '2026-06-12 09:00:00', 'Voisins du dessus',  "Les voisins du dessus refont leur sol jusqu'au 20 juin, prévoir des bouchons d'oreille."],
            ['marie',   '2026-06-15 21:30:00', 'Boulangerie',        "La boulangerie en bas de la rue est fermée le mardi, prévoir le pain à l'avance."],
            ['lucas',   '2026-06-19 14:00:00', 'Local à vélos',      "La clé du local à vélos est dans le tiroir de l'entrée."],
            ['antonin', '2026-06-23 08:00:00', 'Poubelles',          "Sortie des poubelles : jaunes le mardi soir, ménagères le jeudi soir."],
        ];

        foreach ($notes as [$username, $createdAt, $title, $content]) {
            $note = new Note();
            $note->setAuthor($users[$username]);
            $note->setTitle($title);
            $note->setContent($content);
            $note->setCreatedAt(new \DateTimeImmutable($createdAt));
            $manager->persist($note);
        }
    }
}
