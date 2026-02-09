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
final class Version20260208164719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add content_removal_request table for tracking content removal requests';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content_removal_request (email VARCHAR(255) NOT NULL, type VARCHAR(32) NOT NULL, message LONGTEXT NOT NULL, event_urls JSON DEFAULT NULL, status VARCHAR(32) NOT NULL, processed_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, event_id INT NOT NULL, processed_by_id INT DEFAULT NULL, INDEX IDX_5BA2093571F7E88B (event_id), INDEX IDX_5BA209352FFD4FD3 (processed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE content_removal_request ADD CONSTRAINT FK_5BA2093571F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_removal_request ADD CONSTRAINT FK_5BA209352FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE content_removal_request DROP FOREIGN KEY FK_5BA2093571F7E88B');
        $this->addSql('ALTER TABLE content_removal_request DROP FOREIGN KEY FK_5BA209352FFD4FD3');
        $this->addSql('DROP TABLE content_removal_request');
    }
}
