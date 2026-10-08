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

final class Version20261008085732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen the Google profile picture URL of the members: some exceed 255 characters and failed the Google login';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth CHANGE google_profile_picture google_profile_picture VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth CHANGE google_profile_picture google_profile_picture VARCHAR(255) DEFAULT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
