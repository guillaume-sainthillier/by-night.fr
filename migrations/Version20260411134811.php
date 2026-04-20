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

final class Version20260411134811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate ContentRemovalRequest from ManyToOne(Event) to ManyToMany(Event)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE content_removal_request_event (content_removal_request_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_6FBA028BADFB9B9D (content_removal_request_id), INDEX IDX_6FBA028B71F7E88B (event_id), PRIMARY KEY (content_removal_request_id, event_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE content_removal_request_event ADD CONSTRAINT FK_6FBA028BADFB9B9D FOREIGN KEY (content_removal_request_id) REFERENCES content_removal_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_removal_request_event ADD CONSTRAINT FK_6FBA028B71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');

        // Copy existing event_id data into the join table
        $this->addSql('INSERT INTO content_removal_request_event (content_removal_request_id, event_id) SELECT id, event_id FROM content_removal_request WHERE event_id IS NOT NULL');

        // Drop old foreign key and column
        $this->addSql('ALTER TABLE content_removal_request DROP FOREIGN KEY FK_5BA209357B02BF4E');
        $this->addSql('DROP INDEX IDX_5BA209357B02BF4E ON content_removal_request');
        $this->addSql('ALTER TABLE content_removal_request DROP event_id');
    }

    public function down(Schema $schema): void
    {
        // Re-add event_id column
        $this->addSql('ALTER TABLE content_removal_request ADD event_id INT NOT NULL');

        // Copy first event back from join table
        $this->addSql('UPDATE content_removal_request crr SET crr.event_id = (SELECT cre.event_id FROM content_removal_request_event cre WHERE cre.content_removal_request_id = crr.id LIMIT 1)');

        // Add foreign key
        $this->addSql('ALTER TABLE content_removal_request ADD CONSTRAINT FK_5BA209357B02BF4E FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_5BA209357B02BF4E ON content_removal_request (event_id)');

        // Drop join table
        $this->addSql('ALTER TABLE content_removal_request_event DROP FOREIGN KEY FK_6FBA028BADFB9B9D');
        $this->addSql('ALTER TABLE content_removal_request_event DROP FOREIGN KEY FK_6FBA028B71F7E88B');
        $this->addSql('DROP TABLE content_removal_request_event');
    }
}
