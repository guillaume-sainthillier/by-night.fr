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
 * Rename French column names to English.
 */
final class Version20260131135307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename French column names to English (interets, fb_interets, nouvelles_soirees, update_soirees)';
    }

    public function up(Schema $schema): void
    {
        // Event table
        $this->addSql('ALTER TABLE `event` RENAME COLUMN fb_interets TO fb_interests');
        $this->addSql('ALTER TABLE `event` RENAME COLUMN interets TO interests');

        // ParserHistory table
        $this->addSql('ALTER TABLE parser_history RENAME COLUMN nouvelles_soirees TO new_events');
        $this->addSql('ALTER TABLE parser_history RENAME COLUMN update_soirees TO updated_events');
    }

    public function down(Schema $schema): void
    {
        // Event table
        $this->addSql('ALTER TABLE `event` RENAME COLUMN fb_interests TO fb_interets');
        $this->addSql('ALTER TABLE `event` RENAME COLUMN interests TO interets');

        // ParserHistory table
        $this->addSql('ALTER TABLE parser_history RENAME COLUMN new_events TO nouvelles_soirees');
        $this->addSql('ALTER TABLE parser_history RENAME COLUMN updated_events TO update_soirees');
    }
}
