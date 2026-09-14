<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les images jointes aux notes et aux travaux.
 *
 * Une image appartient à exactement un parent : `note_id` ou `work_id`. Aucune
 * contrainte CHECK ne le garantit en base (Doctrine ne les introspecte pas et
 * les diffs suivants les ignoreraient) : c'est `ImageUploadProcessor` qui
 * l'impose.
 *
 * `ON DELETE CASCADE` n'est qu'un filet. La suppression normale passe par la
 * cascade ORM, seule à effacer aussi les fichiers du disque.
 */
final class Version20260914210728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Images jointes aux notes et aux travaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, stored_name VARCHAR(64) NOT NULL, mime_type VARCHAR(32) NOT NULL, size INT NOT NULL, width INT NOT NULL, height INT NOT NULL, created_at DATETIME NOT NULL, note_id INT DEFAULT NULL, work_id INT DEFAULT NULL, INDEX IDX_C53D045F26ED0855 (note_id), INDEX IDX_C53D045FBB3453DB (work_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F26ED0855 FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045FBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F26ED0855');
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045FBB3453DB');
        $this->addSql('DROP TABLE image');
    }
}
