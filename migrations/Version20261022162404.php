<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022162404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The publishers, owning the events of the members: one per member who published, named after them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE publisher (name VARCHAR(180) NOT NULL, slug VARCHAR(128) NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_9CE8D546989D9B62 (slug), INDEX IDX_9CE8D5467E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE publisher ADD CONSTRAINT FK_9CE8D5467E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // The member's name and slug (unique already), dated by their first event
        $this->addSql('INSERT INTO publisher (name, slug, owner_id, created_at, updated_at) SELECT u.username, u.slug, u.id, MIN(e.created_at), NOW() FROM `user` u INNER JOIN `event` e ON e.user_id = u.id GROUP BY u.id ORDER BY u.id');

        // Nothing rebuilds the 6 GB event table: an INSTANT column filled before its indexes exist, the indexes built in
        // place while it stays writable, and the foreign key added in place, which MySQL only allows with the checks off
        $this->addSql('ALTER TABLE `event` ADD publisher_id INT DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('UPDATE `event` e INNER JOIN publisher p ON p.owner_id = e.user_id SET e.publisher_id = p.id');
        $this->addSql('CREATE INDEX IDX_3BAE0AA740C86FCE ON `event` (publisher_id) ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('CREATE UNIQUE INDEX event_publisher_external_reference_unique ON `event` (publisher_id, external_reference) ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('DROP INDEX event_user_external_reference_unique ON `event` ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA740C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id) ON DELETE SET NULL, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA740C86FCE');
        $this->addSql('CREATE UNIQUE INDEX event_user_external_reference_unique ON `event` (user_id, external_reference) ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('DROP INDEX event_publisher_external_reference_unique ON `event`');
        $this->addSql('DROP INDEX IDX_3BAE0AA740C86FCE ON `event`');
        $this->addSql('ALTER TABLE `event` DROP COLUMN publisher_id, ALGORITHM=INSTANT');
        $this->addSql('DROP TABLE publisher');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
