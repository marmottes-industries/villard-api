<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Introduit les pièces (`room`) et bascule l'inventaire dessus.
 *
 * Les catégories en base — « Cuisine », « Salle de bain », « Chambre »… — sont
 * en réalité des pièces, mais `category` est volontairement commune à tous les
 * logements. Elle ne peut donc pas se décliner : impossible d'avoir « Chambre
 * 1 » et « Chambre 2 » dans un logement sans polluer les autres.
 *
 * La reprise se fait ici plutôt que dans une commande : la sortir laisserait,
 * entre la migration et son passage, un inventaire de production intégralement
 * sans pièce. La clé de dédoublonnage est le couple
 * `(inventory_item.property_id, category.name)` et non `category.id`, puisqu'une
 * même catégorie globale doit donner une pièce distincte par logement.
 *
 * `category_id` n'est pas supprimée mais seulement relâchée en nullable : les
 * clients déjà installés continuent de l'écrire le temps de leur mise à jour.
 * Le retrait est prévu pour la prochaine majeure.
 */
final class Version20260812091248 extends AbstractMigration
{
    /**
     * Correspondance historique nom de catégorie → type de pièce. Dupliquée
     * dans {@see \App\Command\ImportRoomsFromCategoriesCommand} : une migration
     * ne doit dépendre d'aucun code applicatif, qui peut changer après coup.
     */
    private const TYPE_BY_NAME = [
        'Cuisine' => 'kitchen',
        'Salle de bain' => 'bathroom',
        'Chambre' => 'bedroom',
        'Salon' => 'living_room',
        'Cave' => 'cellar',
        'Extérieur' => 'outdoor',
    ];

    /** Ordre d'affichage canonique. Les pièces hors table restent à 0. */
    private const POSITION_BY_NAME = [
        'Cuisine' => 0,
        'Salon' => 1,
        'Chambre' => 2,
        'Salle de bain' => 3,
        'Cave' => 4,
        'Extérieur' => 5,
    ];

    public function getDescription(): string
    {
        return 'Room table, room_id on inventory_item and work, backfill of rooms from the legacy per-property categories';
    }

    public function up(Schema $schema): void
    {
        // 1. Structure. L'index unique interdit deux pièces homonymes dans un
        //    même logement, ce que l'INSERT ci-dessous garantit déjà.
        $this->addSql('CREATE TABLE room (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(32) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, archived TINYINT DEFAULT 0 NOT NULL, property_id INT NOT NULL, INDEX IDX_729F519B549213EC (property_id), UNIQUE INDEX UNIQ_ROOM_PROPERTY_NAME (property_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F519B549213EC FOREIGN KEY (property_id) REFERENCES property (id)');

        // 2. Colonnes de rattachement. Elles restent nullables : un article
        //    peut n'appartenir à aucune pièce, et la plupart des travaux
        //    portent sur le logement entier.
        $this->addSql('ALTER TABLE inventory_item ADD room_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE work ADD room_id INT DEFAULT NULL');

        // 3. Une pièce par couple (logement, nom de catégorie) réellement
        //    utilisé. Les catégories qui ne servent qu'aux courses
        //    (« Épicerie », « Produits frais ») n'en créent donc aucune.
        $this->addSql('INSERT INTO room (property_id, name, type, position, archived)
            SELECT DISTINCT i.property_id, c.name, NULL, 0, 0
              FROM inventory_item i
              INNER JOIN category c ON c.id = i.category_id');

        // 4. Typage et ordre d'affichage des pièces ainsi créées.
        foreach (self::TYPE_BY_NAME as $name => $type) {
            $this->addSql('UPDATE room SET type = :type WHERE name = :name AND type IS NULL', ['type' => $type, 'name' => $name]);
        }

        foreach (self::POSITION_BY_NAME as $name => $position) {
            $this->addSql('UPDATE room SET position = :position WHERE name = :name', ['position' => $position, 'name' => $name]);
        }

        // 5. Rattachement des articles, avant tout relâchement de contrainte.
        $this->addSql('UPDATE inventory_item i
            INNER JOIN category c ON c.id = i.category_id
            INNER JOIN room r ON r.property_id = i.property_id AND r.name = c.name
            SET i.room_id = r.id');

        // 6. Seulement maintenant : relâcher `category_id`. L'inverser avec
        //    l'étape 5 laisserait des articles orphelins irrécupérables.
        $this->addSql('ALTER TABLE inventory_item CHANGE category_id category_id INT DEFAULT NULL');

        // 7. Contraintes et index sur les nouvelles colonnes. `SET NULL` plutôt
        //    que `RESTRICT` : supprimer une pièce détache ses articles au lieu
        //    de faire échouer la suppression.
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_55BDEA3054177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_55BDEA3054177093 ON inventory_item (room_id)');
        $this->addSql('ALTER TABLE work ADD CONSTRAINT FK_534E688054177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_534E688054177093 ON work (room_id)');
    }

    public function down(Schema $schema): void
    {
        // Reconstituer `category_id` pour les articles créés par un client déjà
        // basculé, qui n'en ont plus. La jointure se fait sur le nom de pièce.
        $this->addSql('UPDATE inventory_item i
            INNER JOIN room r ON r.id = i.room_id
            INNER JOIN category c ON c.name = r.name
            SET i.category_id = c.id
            WHERE i.category_id IS NULL');

        // Seule perte assumée du rollback : un article rangé dans une pièce
        // sans catégorie homonyme (« Chambre 2 », « Cabane à skis ») n'a aucune
        // valeur possible, et la colonne redevient NOT NULL juste après.
        $this->addSql('DELETE FROM inventory_item WHERE category_id IS NULL');
        $this->addSql('ALTER TABLE inventory_item CHANGE category_id category_id INT NOT NULL');

        $this->addSql('ALTER TABLE work DROP FOREIGN KEY FK_534E688054177093');
        $this->addSql('DROP INDEX IDX_534E688054177093 ON work');
        $this->addSql('ALTER TABLE work DROP room_id');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_55BDEA3054177093');
        $this->addSql('DROP INDEX IDX_55BDEA3054177093 ON inventory_item');
        $this->addSql('ALTER TABLE inventory_item DROP room_id');
        $this->addSql('ALTER TABLE room DROP FOREIGN KEY FK_729F519B549213EC');
        $this->addSql('DROP TABLE room');
    }
}
