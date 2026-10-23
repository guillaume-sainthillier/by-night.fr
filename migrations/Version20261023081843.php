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

final class Version20261023081843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The publisher of each import and export: the one of the member who started it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import ADD publisher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_import ADD CONSTRAINT FK_AFD7B94F40C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_AFD7B94F40C86FCE ON event_import (publisher_id)');
        $this->addSql('ALTER TABLE event_export ADD publisher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_export ADD CONSTRAINT FK_701561C640C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_701561C640C86FCE ON event_export (publisher_id)');

        // A member had one publisher at most until now: their jobs were its. A member without one keeps none, and
        // their jobs (30 days at most, app:transfers:cleanup) are listed nowhere
        $this->addSql('UPDATE event_import j INNER JOIN publisher p ON p.owner_id = j.user_id SET j.publisher_id = p.id');
        $this->addSql('UPDATE event_export j INNER JOIN publisher p ON p.owner_id = j.user_id SET j.publisher_id = p.id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import DROP FOREIGN KEY FK_AFD7B94F40C86FCE');
        $this->addSql('DROP INDEX IDX_AFD7B94F40C86FCE ON event_import');
        $this->addSql('ALTER TABLE event_import DROP publisher_id');
        $this->addSql('ALTER TABLE event_export DROP FOREIGN KEY FK_701561C640C86FCE');
        $this->addSql('DROP INDEX IDX_701561C640C86FCE ON event_export');
        $this->addSql('ALTER TABLE event_export DROP publisher_id');
    }
}
