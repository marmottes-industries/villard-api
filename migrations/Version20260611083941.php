<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611083941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications: add user.email, occupation.end_notified_at, device_token table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, platform VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_99B2415C7E3C61F9 (owner_id), UNIQUE INDEX UNIQ_DEVICE_TOKEN (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_99B2415C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE occupation ADD end_notified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_token DROP FOREIGN KEY FK_99B2415C7E3C61F9');
        $this->addSql('DROP TABLE device_token');
        $this->addSql('ALTER TABLE occupation DROP end_notified_at');
        $this->addSql('ALTER TABLE user DROP email');
    }
}
