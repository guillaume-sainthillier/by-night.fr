<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190403123547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD external_id VARCHAR(127) DEFAULT NULL');
        $this->addSql('CREATE INDEX agenda_external_id_idx ON Agenda (external_id)');
        $this->addSql('ALTER TABLE Place ADD external_id VARCHAR(127) DEFAULT NULL');
        $this->addSql('CREATE INDEX place_external_id_idx ON Place (external_id)');
        $this->addSql('UPDATE Agenda SET external_id = CONCAT(\'FB-\', facebook_event_id)');
        $this->addSql('UPDATE Place SET external_id = CONCAT(\'FB-\', facebook_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_external_id_idx ON Agenda');
        $this->addSql('ALTER TABLE Agenda DROP external_id');
        $this->addSql('DROP INDEX place_external_id_idx ON Place');
        $this->addSql('ALTER TABLE Place DROP external_id');
    }
}
