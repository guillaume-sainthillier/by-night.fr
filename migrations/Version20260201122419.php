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
 * Migration to normalize event status:
 * - Rename `status` column to `status_message` (preserves custom status text)
 * - Add new `status` column as enum (scheduled, postponed, cancelled, sold_out).
 *
 * Run `app:events:migrate-status` command after this migration to populate the new status column.
 */
final class Version20260201122419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize event status: rename status to status_message, add new enum status column';
    }

    public function up(Schema $schema): void
    {
        // Rename status to status_message
        $this->addSql('ALTER TABLE `event` CHANGE status status_message VARCHAR(255) DEFAULT NULL');

        // Add new status column for enum values
        $this->addSql('ALTER TABLE `event` ADD status VARCHAR(16) DEFAULT NULL AFTER hours');
    }

    public function down(Schema $schema): void
    {
        // Remove the new status column
        $this->addSql('ALTER TABLE `event` DROP status');

        // Rename status_message back to status
        $this->addSql('ALTER TABLE `event` CHANGE status_message status VARCHAR(16) DEFAULT NULL');
    }
}
