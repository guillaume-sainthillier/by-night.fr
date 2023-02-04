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
final class Version20221105093548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE event SET source = REPLACE(source, 'https://data.datatourisme.gouv.fr', 'https://data.datatourisme.fr') WHERE external_origin = 'datatourisme' AND source IS NOT NULL;");
        $this->addSql("UPDATE event SET url = REPLACE(url, 'https://data.datatourisme.gouv.fr', 'https://data.datatourisme.fr') WHERE external_origin = 'datatourisme' AND url IS NOT NULL;");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
