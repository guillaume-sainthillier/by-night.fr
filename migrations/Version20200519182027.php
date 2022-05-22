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
final class Version20200519182027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE event ADD phone_contacts JSON DEFAULT NULL, ADD mail_contacts JSON DEFAULT NULL, ADD website_contacts JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user_event DROP FOREIGN KEY FK_FD283F6971F7E88B');
        $this->addSql('ALTER TABLE user_event ADD CONSTRAINT FK_D96CF1FF71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_5BC96BF071F7E88B');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C71F7E88B');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_5BC96BF071F7E88B FOREIGN KEY (event_id) REFERENCES Agenda (id)');
        $this->addSql('ALTER TABLE event DROP phone_contacts, DROP mail_contacts, DROP website_contacts');
        $this->addSql('ALTER TABLE user_event DROP FOREIGN KEY FK_D96CF1FF71F7E88B');
        $this->addSql('ALTER TABLE user_event ADD CONSTRAINT FK_FD283F6971F7E88B FOREIGN KEY (event_id) REFERENCES Agenda (id)');
    }
}
