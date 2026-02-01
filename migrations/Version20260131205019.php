<?php

declare(strict_types=1);

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

/**
 * Create Tag table and migrate category/theme from strings to proper entities.
 */
final class Version20260131205019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Tag table with event relationships (category ManyToOne, themes ManyToMany)';
    }

    public function up(Schema $schema): void
    {
        // Create tag table
        $this->addSql('CREATE TABLE tag (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(128) NOT NULL,
            slug VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_389B783989D9B62 (slug),
            INDEX tag_name_idx (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create event_tag join table for ManyToMany themes
        $this->addSql('CREATE TABLE event_tag (
            event_id INT NOT NULL,
            tag_id INT NOT NULL,
            INDEX IDX_12916A8371F7E88B (event_id),
            INDEX IDX_12916A83BAD26311 (tag_id),
            PRIMARY KEY(event_id, tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign keys for event_tag join table
        $this->addSql('ALTER TABLE event_tag ADD CONSTRAINT FK_12916A8371F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_tag ADD CONSTRAINT FK_12916A83BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');

        // Add category_id column to event table (ManyToOne)
        $this->addSql('ALTER TABLE `event` ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA712469DE2 FOREIGN KEY (category_id) REFERENCES tag (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3BAE0AA712469DE2 ON `event` (category_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove category_id from event table
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA712469DE2');
        $this->addSql('DROP INDEX IDX_3BAE0AA712469DE2 ON `event`');
        $this->addSql('ALTER TABLE `event` DROP category_id');

        // Remove event_tag join table
        $this->addSql('ALTER TABLE event_tag DROP FOREIGN KEY FK_12916A8371F7E88B');
        $this->addSql('ALTER TABLE event_tag DROP FOREIGN KEY FK_12916A83BAD26311');
        $this->addSql('DROP TABLE event_tag');

        // Remove tag table
        $this->addSql('DROP TABLE tag');
    }
}
