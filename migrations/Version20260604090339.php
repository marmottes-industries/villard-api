<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260604090339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_item (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, quantity INT DEFAULT 1 NOT NULL, category_id INT NOT NULL, INDEX IDX_55BDEA3012469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content INT NOT NULL, created_at DATETIME NOT NULL, author_id INT NOT NULL, INDEX IDX_CFBDFA14F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE occupation (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, notes LONGTEXT DEFAULT NULL, occupant_id INT NOT NULL, INDEX IDX_2F87D5167BAA0E5 (occupant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shopping_item (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, quantity INT DEFAULT 1 NOT NULL, purchased TINYINT DEFAULT 0 NOT NULL, category_id INT DEFAULT NULL, INDEX IDX_6612795F12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_55BDEA3012469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE occupation ADD CONSTRAINT FK_2F87D5167BAA0E5 FOREIGN KEY (occupant_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE shopping_item ADD CONSTRAINT FK_6612795F12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_55BDEA3012469DE2');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14F675F31B');
        $this->addSql('ALTER TABLE occupation DROP FOREIGN KEY FK_2F87D5167BAA0E5');
        $this->addSql('ALTER TABLE shopping_item DROP FOREIGN KEY FK_6612795F12469DE2');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE inventory_item');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE occupation');
        $this->addSql('DROP TABLE shopping_item');
        $this->addSql('DROP TABLE user');
    }
}
