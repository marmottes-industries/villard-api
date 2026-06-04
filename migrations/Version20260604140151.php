<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260604140151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable UUID on user for JWT identity (decouples token from username)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD uuid BINARY(16) DEFAULT NULL');

        foreach ($this->connection->fetchAllAssociative('SELECT id FROM user') as $row) {
            $this->addSql(
                'UPDATE user SET uuid = :uuid WHERE id = :id',
                ['uuid' => Uuid::v4()->toBinary(), 'id' => $row['id']],
            );
        }

        $this->addSql('ALTER TABLE user MODIFY uuid BINARY(16) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649D17F50A6 ON user (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649D17F50A6 ON user');
        $this->addSql('ALTER TABLE user DROP uuid');
    }
}
