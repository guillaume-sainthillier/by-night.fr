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
final class Version20200509205750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('RENAME TABLE `Agenda` TO `event`');
        $this->addSql('RENAME TABLE `Calendrier` TO `user_event`');
        $this->addSql('RENAME TABLE `Comment` TO `comment_tmp`');
        $this->addSql('RENAME TABLE `comment_tmp` TO `comment`');
        $this->addSql('RENAME TABLE `Exploration` TO `parser_data`');
        $this->addSql('RENAME TABLE `HistoriqueMaj` TO `parser_history`');
        $this->addSql('RENAME TABLE `Info` TO `oauth`');
        $this->addSql('RENAME TABLE `Place` TO `place_tmp`');
        $this->addSql('RENAME TABLE `place_tmp` TO `place`');
        $this->addSql('RENAME TABLE `User` TO `user_tmp`');
        $this->addSql('RENAME TABLE `user_tmp` TO `user`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
