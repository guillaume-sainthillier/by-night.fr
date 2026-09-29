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

final class Version20260927093729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a city go when it is deleted although members chose it on their profile: their city becomes empty';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_2DA179778BAC62AF`');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D6498BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D6498BAC62AF');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_2DA179778BAC62AF` FOREIGN KEY (city_id) REFERENCES admin_zone (id)');
    }
}
