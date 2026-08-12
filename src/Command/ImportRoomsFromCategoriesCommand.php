<?php

namespace App\Command;

use App\Entity\InventoryItem;
use App\Entity\Property;
use App\Entity\Room;
use App\Enum\RoomType;
use App\Repository\InventoryItemRepository;
use App\Repository\PropertyRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills the room of inventory items that still only carry a legacy category.
 *
 * This is NOT the initial data migration — Version20260812091248 already did
 * that for everything existing when it ran. This command exists because, during
 * the whole transition window, mobile builds that predate the feature keep
 * creating items with a category and no room. It reconciles them, as often as
 * needed.
 *
 *   php bin/console app:rooms:import-from-categories --dry-run
 *   php bin/console app:rooms:import-from-categories --property=les-tennis
 *
 * Idempotent: only items with room = null and category != null are considered,
 * and a room is reused whenever one already carries the category's name in that
 * property. Running it twice in a row is a no-op.
 */
#[AsCommand(
    name: 'app:rooms:import-from-categories',
    description: 'Backfill inventory rooms from legacy categories, per property',
)]
final class ImportRoomsFromCategoriesCommand extends Command
{
    /**
     * Kept in sync by hand with {@see \DoctrineMigrations\Version20260812091248}:
     * a migration must not depend on application code that may change later.
     */
    private const TYPE_BY_NAME = [
        'cuisine' => RoomType::KITCHEN,
        'salle de bain' => RoomType::BATHROOM,
        'chambre' => RoomType::BEDROOM,
        'salon' => RoomType::LIVING_ROOM,
        'cave' => RoomType::CELLAR,
        'exterieur' => RoomType::OUTDOOR,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyRepository $properties,
        private readonly RoomRepository $rooms,
        private readonly InventoryItemRepository $items,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'Restrict to a single property, by slug')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $slug = $input->getOption('property');

        if (null !== $slug) {
            $property = $this->properties->findOneBy(['slug' => $slug]);

            if (null === $property) {
                $io->error(sprintf('No property with slug "%s".', $slug));

                return Command::FAILURE;
            }

            $targets = [$property];
        } else {
            $targets = $this->properties->findAll();
        }

        $rows = [];
        $totalAttached = 0;

        foreach ($targets as $property) {
            $pending = $this->items->createQueryBuilder('i')
                ->andWhere('i.property = :property')
                ->andWhere('i.room IS NULL')
                ->andWhere('i.category IS NOT NULL')
                ->setParameter('property', $property)
                ->getQuery()
                ->getResult();

            $created = 0;
            $reused = 0;
            $attached = 0;
            $cache = [];

            /** @var InventoryItem $item */
            foreach ($pending as $item) {
                $name = $item->getCategory()?->getName();

                if (null === $name) {
                    continue;
                }

                if (!isset($cache[$name])) {
                    $room = $this->rooms->findOneBy(['property' => $property, 'name' => $name]);

                    if (null === $room) {
                        $room = $this->createRoom($property, $name);
                        $this->em->persist($room);
                        ++$created;
                    } else {
                        ++$reused;
                    }

                    $cache[$name] = $room;
                }

                $item->setRoom($cache[$name]);
                ++$attached;
            }

            $totalAttached += $attached;
            $rows[] = [$property->getSlug(), $created, $reused, $attached];
        }

        $io->table(['Logement', 'Pièces créées', 'Pièces réutilisées', 'Articles rattachés'], $rows);

        if ($dryRun) {
            $io->warning('Mode simulation : aucune écriture.');

            return Command::SUCCESS;
        }

        if (0 === $totalAttached) {
            $io->success('Rien à rattacher : tous les articles ont déjà une pièce.');

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(sprintf('%d article(s) rattaché(s) à une pièce.', $totalAttached));

        return Command::SUCCESS;
    }

    /**
     * Une pièce créée à la volée prend la première position libre : elle arrive
     * après celles que le gestionnaire a déjà ordonnées à la main.
     */
    private function createRoom(Property $property, string $name): Room
    {
        $room = new Room();
        $room->setName($name);
        $room->setType(self::TYPE_BY_NAME[$this->normalize($name)] ?? null);
        $room->setPosition($this->rooms->nextPositionFor($property));
        $room->setProperty($property);

        return $room;
    }

    /**
     * Insensibilise la correspondance à la casse et aux accents : « Extérieur »
     * et « exterieur » désignent la même pièce.
     */
    private function normalize(string $name): string
    {
        $ascii = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);

        return \is_string($ascii) ? trim($ascii) : mb_strtolower(trim($name));
    }
}
