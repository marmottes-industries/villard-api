<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609092724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE work CHANGE description description LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE scheduled_for scheduled_for DATE DEFAULT NULL, CHANGE author_id author_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE work CHANGE description description VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) DEFAULT \'suggested\', CHANGE scheduled_for scheduled_for DATETIME DEFAULT NULL, CHANGE author_id author_id INT DEFAULT NULL');
    }
}
