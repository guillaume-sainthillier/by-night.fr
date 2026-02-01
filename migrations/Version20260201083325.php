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
final class Version20260201083325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_tag RENAME INDEX idx_12916a8371f7e88b TO IDX_1246725071F7E88B');
        $this->addSql('ALTER TABLE event_tag RENAME INDEX idx_12916a83bad26311 TO IDX_12467250BAD26311');
        $this->addSql('ALTER TABLE tag CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_tag RENAME INDEX idx_1246725071f7e88b TO IDX_12916A8371F7E88B');
        $this->addSql('ALTER TABLE event_tag RENAME INDEX idx_12467250bad26311 TO IDX_12916A83BAD26311');
        $this->addSql('ALTER TABLE tag CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
