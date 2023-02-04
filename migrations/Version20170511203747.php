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

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170511203747 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE zip_city ADD slug VARCHAR(201) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FBE2D3F4989D9B62 ON zip_city (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('DROP INDEX UNIQ_FBE2D3F4989D9B62 ON zip_city');
        $this->addSql('ALTER TABLE zip_city DROP slug');
    }
}
