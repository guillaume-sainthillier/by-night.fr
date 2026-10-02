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

final class Version20261002111144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the image names of the events and users: OldMediaController looks the legacy /media/cache and /uploads URLs up by image_name OR image_system_name, a full scan of the 6 GB event table on every miss';
    }

    public function up(Schema $schema): void
    {
        // One index per column so MySQL can answer the OR with an index_merge union;
        // built online (INPLACE, LOCK=NONE): the event table keeps taking writes during the build
        $this->addSql('ALTER TABLE `event` ADD INDEX event_image_name_idx (image_name), ADD INDEX event_image_system_name_idx (image_system_name), ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('ALTER TABLE `user` ADD INDEX user_image_name_idx (image_name), ADD INDEX user_image_system_name_idx (image_system_name), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP INDEX event_image_name_idx, DROP INDEX event_image_system_name_idx, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('ALTER TABLE `user` DROP INDEX user_image_name_idx, DROP INDEX user_image_system_name_idx, ALGORITHM=INPLACE, LOCK=NONE');
    }
}
