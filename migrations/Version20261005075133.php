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

final class Version20261005075133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the cities by metropolis flag: the métropoles of the home page read the 88k cities through admin_zone_type_name_idx to keep the 22 flagged ones';
    }

    public function up(Schema $schema): void
    {
        // Built online (INPLACE, LOCK=NONE): admin_zone keeps taking writes during the build
        $this->addSql('ALTER TABLE admin_zone ADD INDEX admin_zone_type_metropolis_idx (type, is_metropolis), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone DROP INDEX admin_zone_type_metropolis_idx, ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
