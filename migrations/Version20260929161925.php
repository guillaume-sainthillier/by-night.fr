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

final class Version20260929161925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the OAuth tokens as text: Google access tokens outgrew 255 characters and broke the sign-in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth CHANGE facebook_access_token facebook_access_token LONGTEXT DEFAULT NULL, CHANGE google_access_token google_access_token LONGTEXT DEFAULT NULL, CHANGE twitter_access_token twitter_access_token LONGTEXT DEFAULT NULL, CHANGE facebook_refresh_token facebook_refresh_token LONGTEXT DEFAULT NULL, CHANGE google_refresh_token google_refresh_token LONGTEXT DEFAULT NULL, CHANGE twitter_refresh_token twitter_refresh_token LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth CHANGE facebook_access_token facebook_access_token VARCHAR(511) DEFAULT NULL, CHANGE google_access_token google_access_token VARCHAR(255) DEFAULT NULL, CHANGE twitter_access_token twitter_access_token VARCHAR(255) DEFAULT NULL, CHANGE facebook_refresh_token facebook_refresh_token VARCHAR(511) DEFAULT NULL, CHANGE google_refresh_token google_refresh_token VARCHAR(255) DEFAULT NULL, CHANGE twitter_refresh_token twitter_refresh_token VARCHAR(255) DEFAULT NULL');
    }
}
