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
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190917104351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Info ADD eventbrite_id VARCHAR(255) DEFAULT NULL, ADD eventbrite_access_token VARCHAR(255) DEFAULT NULL, ADD eventbrite_token_secret VARCHAR(255) DEFAULT NULL, ADD eventbrite_refresh_token VARCHAR(255) DEFAULT NULL, ADD eventbrite_email VARCHAR(255) DEFAULT NULL, ADD eventbrite_expires_in VARCHAR(255) DEFAULT NULL, ADD eventbrite_nickname VARCHAR(255) DEFAULT NULL, ADD eventbrite_realname VARCHAR(255) DEFAULT NULL, ADD eventbrite_profile_picture VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Info DROP eventbrite_id, DROP eventbrite_access_token, DROP eventbrite_token_secret, DROP eventbrite_refresh_token, DROP eventbrite_email, DROP eventbrite_expires_in, DROP eventbrite_nickname, DROP eventbrite_realname, DROP eventbrite_profile_picture');
    }
}
