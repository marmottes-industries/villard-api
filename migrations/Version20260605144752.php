<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605144752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add state, note and location to inventory_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inventory_item ADD state VARCHAR(255) DEFAULT 'ok' NOT NULL");
        $this->addSql('ALTER TABLE inventory_item ADD note VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_item ADD location VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_item DROP state');
        $this->addSql('ALTER TABLE inventory_item DROP note');
        $this->addSql('ALTER TABLE inventory_item DROP location');
    }
}
