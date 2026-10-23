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

final class Version20261023132810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The daily audience of the event pages, read from Umami';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_visit_stat (day DATE NOT NULL, visitors INT DEFAULT 0 NOT NULL, ticket_clicks INT DEFAULT 0 NOT NULL, id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, INDEX event_visit_stat_day_idx (day), UNIQUE INDEX event_visit_stat_unique (event_id, day), INDEX IDX_A70EDBCC71F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE event_visit_stat ADD CONSTRAINT FK_A70EDBCC71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_visit_stat');
    }
}
