<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Couleur d'accent par logement, et correction du nom du logement historique.
 *
 * « Les Marmottes » est le nom du service, pas celui de l'appartement de
 * Villard-de-Lans : {@see Version20260810155317} l'avait repris par erreur
 * comme nom du logement de reprise. Le renommage est fait ici plutôt qu'en
 * corrigeant la migration précédente, déjà appliquée. Il est conditionné au
 * slug historique : une base déjà renommée à la main n'est pas touchée.
 */
final class Version20260811094500 extends AbstractMigration
{
    private const LEGACY_SLUG = 'les-marmottes';
    private const RENAMED_SLUG = 'les-tennis';

    public function getDescription(): string
    {
        return 'Property: accent_color column, and rename of the legacy property to « Les Tennis »';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE property ADD accent_color VARCHAR(32) DEFAULT 'forest' NOT NULL");

        $this->addSql(
            'UPDATE property SET name = :name, slug = :newSlug WHERE slug = :oldSlug',
            ['name' => 'Les Tennis', 'newSlug' => self::RENAMED_SLUG, 'oldSlug' => self::LEGACY_SLUG]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE property SET name = :name, slug = :oldSlug WHERE slug = :newSlug',
            ['name' => 'Les Marmottes', 'oldSlug' => self::LEGACY_SLUG, 'newSlug' => self::RENAMED_SLUG]
        );

        $this->addSql('ALTER TABLE property DROP accent_color');
    }
}
