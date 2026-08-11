<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-logements : tables `property` / `property_member`, rattachement des
 * cinq ressources métier à un logement, et reprise de l'existant.
 *
 * Le squelette vient de `doctrine:migrations:diff`, mais l'ordre a été repris
 * à la main. Les colonnes `property_id` sont créées nullables, remplies, puis
 * contraintes — sinon `ALTER TABLE ... ADD property_id INT NOT NULL` échoue
 * dès qu'une table contient déjà des lignes. Le `down()` relâche
 * symétriquement les contraintes des tables métier avant de supprimer
 * `property`, ce que la version auto-générée faisait dans le mauvais ordre.
 */
final class Version20260810155317 extends AbstractMigration
{
    /**
     * Les cinq tables métier rattachées à un logement, avec le nom de leur
     * clé étrangère et de leur index tels que générés par Doctrine.
     */
    private const SCOPED_TABLES = [
        'inventory_item' => ['fk' => 'FK_55BDEA30549213EC', 'idx' => 'IDX_55BDEA30549213EC'],
        'note' => ['fk' => 'FK_CFBDFA14549213EC', 'idx' => 'IDX_CFBDFA14549213EC'],
        'occupation' => ['fk' => 'FK_2F87D51549213EC', 'idx' => 'IDX_2F87D51549213EC'],
        'shopping_item' => ['fk' => 'FK_6612795F549213EC', 'idx' => 'IDX_6612795F549213EC'],
        'work' => ['fk' => 'FK_534E6880549213EC', 'idx' => 'IDX_534E6880549213EC'],
    ];

    /**
     * Slug du logement historique. Toutes les données antérieures à cette
     * migration lui sont rattachées.
     */
    private const LEGACY_SLUG = 'les-marmottes';

    public function getDescription(): string
    {
        return 'Multi-logements: property + property_member tables, property_id on the 5 business tables, backfill of existing data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL, secondary_location_name VARCHAR(255) DEFAULT NULL, secondary_latitude DOUBLE PRECISION DEFAULT NULL, secondary_longitude DOUBLE PRECISION DEFAULT NULL, archived TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_8BF21CDE989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE property_member (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(32) DEFAULT \'occupant\' NOT NULL, property_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E939504549213EC (property_id), INDEX IDX_E939504A76ED395 (user_id), UNIQUE INDEX UNIQ_PROPERTY_MEMBER (property_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE property_member ADD CONSTRAINT FK_E939504549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE property_member ADD CONSTRAINT FK_E939504A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        // Logement historique. Les coordonnées reprennent les anciennes
        // variables WEATHER_* (Villard-de-Lans + Côte 2000), qui disparaissent
        // de la configuration au profit de ces colonnes.
        $this->addSql(
            'INSERT INTO property (name, slug, city, address, latitude, longitude, timezone, secondary_location_name, secondary_latitude, secondary_longitude, archived)
             VALUES (:name, :slug, :city, NULL, :lat, :lon, :tz, :secName, :secLat, :secLon, 0)',
            [
                'name' => 'Les Marmottes',
                'slug' => self::LEGACY_SLUG,
                'city' => 'Villard-de-Lans',
                'lat' => 45.064757765580204,
                'lon' => 5.548400944891808,
                'tz' => 'Europe/Paris',
                'secName' => 'Côte 2000',
                'secLat' => 45.0186219050606,
                'secLon' => 5.571823469177524,
            ]
        );

        // Tout utilisateur existant devient gestionnaire du logement historique :
        // avant cette migration, chacun avait accès à l'intégralité des données.
        $this->addSql(
            'INSERT INTO property_member (property_id, user_id, role)
             SELECT p.id, u.id, :role FROM user u CROSS JOIN property p WHERE p.slug = :slug',
            ['role' => 'manager', 'slug' => self::LEGACY_SLUG]
        );

        // Colonnes d'abord nullables, puis remplies, puis contraintes.
        foreach (array_keys(self::SCOPED_TABLES) as $table) {
            $this->addSql(\sprintf('ALTER TABLE %s ADD property_id INT DEFAULT NULL', $table));
            $this->addSql(
                \sprintf('UPDATE %s SET property_id = (SELECT id FROM property WHERE slug = :slug)', $table),
                ['slug' => self::LEGACY_SLUG]
            );
            $this->addSql(\sprintf('ALTER TABLE %s MODIFY property_id INT NOT NULL', $table));
        }

        foreach (self::SCOPED_TABLES as $table => $names) {
            $this->addSql(\sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (property_id) REFERENCES property (id)',
                $table,
                $names['fk']
            ));
            $this->addSql(\sprintf('CREATE INDEX %s ON %s (property_id)', $names['idx'], $table));
        }
    }

    public function down(Schema $schema): void
    {
        // Relâcher d'abord les références vers `property`, sinon le DROP TABLE
        // final se heurte aux contraintes des cinq tables métier.
        foreach (self::SCOPED_TABLES as $table => $names) {
            $this->addSql(\sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $names['fk']));
            $this->addSql(\sprintf('DROP INDEX %s ON %s', $names['idx'], $table));
            $this->addSql(\sprintf('ALTER TABLE %s DROP property_id', $table));
        }

        $this->addSql('ALTER TABLE property_member DROP FOREIGN KEY FK_E939504549213EC');
        $this->addSql('ALTER TABLE property_member DROP FOREIGN KEY FK_E939504A76ED395');
        $this->addSql('DROP TABLE property_member');
        $this->addSql('DROP TABLE property');
    }
}
