<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
final class Version20191122135355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda CHANGE is_archive archive TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE Place CHANGE is_junk junk TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE `Comment` CHANGE is_approuve approuve TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda CHANGE archive is_archive TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `Comment` CHANGE approuve is_approuve TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE Place CHANGE junk is_junk TINYINT(1) DEFAULT NULL');
    }
}
