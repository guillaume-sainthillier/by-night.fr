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

final class Version20261023211258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The publishers\' news: the members\' opt-out and the editions sent to each';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD publisher_news TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TABLE publisher_news_delivery (edition VARCHAR(32) NOT NULL, sent_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX publisher_news_delivery_unique (user_id, edition), INDEX IDX_41CF04D8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE publisher_news_delivery ADD CONSTRAINT FK_41CF04D8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publisher_news_delivery');
        $this->addSql('ALTER TABLE `user` DROP publisher_news');
    }
}
