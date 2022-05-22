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
final class Version20190328133920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda CHANGE slug slug VARCHAR(255) NOT NULL, CHANGE path path VARCHAR(61) DEFAULT NULL, CHANGE tweet_post_id tweet_post_id VARCHAR(127) DEFAULT NULL, CHANGE tweet_post_system_id tweet_post_system_id VARCHAR(127) DEFAULT NULL, CHANGE fb_post_id fb_post_id VARCHAR(127) DEFAULT NULL, CHANGE fb_post_system_id fb_post_system_id VARCHAR(127) DEFAULT NULL, CHANGE google_post_id google_post_id VARCHAR(127) DEFAULT NULL, CHANGE google_post_system_id google_post_system_id VARCHAR(127) DEFAULT NULL, CHANGE facebook_event_id facebook_event_id VARCHAR(127) DEFAULT NULL, CHANGE facebook_owner_id facebook_owner_id VARCHAR(31) DEFAULT NULL, CHANGE system_path system_path VARCHAR(61) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda CHANGE slug slug VARCHAR(128) NOT NULL COLLATE utf8_unicode_ci, CHANGE path path VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE system_path system_path VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE tweet_post_id tweet_post_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE facebook_event_id facebook_event_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE tweet_post_system_id tweet_post_system_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE fb_post_id fb_post_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE fb_post_system_id fb_post_system_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE google_post_id google_post_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE google_post_system_id google_post_system_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE facebook_owner_id facebook_owner_id VARCHAR(256) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
