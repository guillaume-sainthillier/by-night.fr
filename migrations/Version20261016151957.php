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

final class Version20261016151957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The members\' event imports and exports, and the reference a member gives their event (unique among theirs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_import (status VARCHAR(16) NOT NULL, format VARCHAR(8) NOT NULL, original_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, success_file_path VARCHAR(255) DEFAULT NULL, error_file_path VARCHAR(255) DEFAULT NULL, row_count INT NOT NULL, event_count INT NOT NULL, processed_count INT NOT NULL, valid_count INT NOT NULL, invalid_count INT NOT NULL, warning_count INT NOT NULL, created_count INT NOT NULL, updated_count INT NOT NULL, unchanged_count INT NOT NULL, failed_count INT NOT NULL, errors JSON NOT NULL, warnings JSON NOT NULL, outcomes JSON NOT NULL, failure_reason LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX event_import_status_idx (status, updated_at), INDEX IDX_AFD7B94FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE event_export (status VARCHAR(16) NOT NULL, format VARCHAR(8) NOT NULL, scope VARCHAR(16) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, total_count INT NOT NULL, exported_count INT NOT NULL, failure_reason LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX event_export_status_idx (status, updated_at), INDEX IDX_701561C6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE event_import ADD CONSTRAINT FK_AFD7B94FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_export ADD CONSTRAINT FK_701561C6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        // A nullable column added in place (INSTANT), then its index built online (INPLACE, LOCK=NONE): the event
        // table keeps taking the imports' writes. Every row has a NULL reference, which the unique index allows.
        $this->addSql('ALTER TABLE event ADD external_reference VARCHAR(100) DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('ALTER TABLE event ADD UNIQUE INDEX event_user_external_reference_unique (user_id, external_reference), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP INDEX event_user_external_reference_unique, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('ALTER TABLE event DROP external_reference');
        $this->addSql('ALTER TABLE event_import DROP FOREIGN KEY FK_AFD7B94FA76ED395');
        $this->addSql('ALTER TABLE event_export DROP FOREIGN KEY FK_701561C6A76ED395');
        $this->addSql('DROP TABLE event_import');
        $this->addSql('DROP TABLE event_export');
    }
}
