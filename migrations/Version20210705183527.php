<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
final class Version20210705183527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE place_metadata (id INT AUTO_INCREMENT NOT NULL, place_id INT NOT NULL, external_id VARCHAR(255) NOT NULL, external_origin VARCHAR(63) NOT NULL, external_updated_at DATETIME DEFAULT NULL, INDEX IDX_654FD397DA6A219 (place_id), INDEX place_metadata_idx (external_id, external_origin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE place_metadata ADD CONSTRAINT FK_654FD397DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX event_external_id_idx ON event');
        $this->addSql('ALTER TABLE event ADD external_origin VARCHAR(63) DEFAULT NULL');
        $this->addSql('CREATE INDEX event_external_id_idx ON event (external_id, external_origin)');
        $this->addSql('DROP INDEX UNIQ_FE9A4A9C9F75D7B0 ON parser_data');
        $this->addSql('ALTER TABLE parser_data ADD external_origin VARCHAR(63) NOT NULL');
        $this->addSql('CREATE INDEX parser_data_idx ON parser_data (external_id, external_origin)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE place_metadata');
        $this->addSql('DROP INDEX event_external_id_idx ON event');
        $this->addSql('ALTER TABLE event DROP external_origin');
        $this->addSql('CREATE INDEX event_external_id_idx ON event (external_id)');
        $this->addSql('DROP INDEX parser_data_idx ON parser_data');
        $this->addSql('ALTER TABLE parser_data DROP external_origin');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE9A4A9C9F75D7B0 ON parser_data (external_id)');
    }
}
