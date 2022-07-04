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
final class Version20220704105654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        foreach ([
                     'sowprog' => 'SP-',
                     'openagenda' => 'OA-',
                     'facebook' => 'FB-',
                     'datatourisme' => 'DT-',
                     'awin.fnac' => 'FS-',
                     'awin.digitick' => 'DGT-',
                     'toulouse.opendata' => 'TOU-',
                     'toulouse.bikini' => 'BKN-',
                 ] as $origin => $prefix) {
            $this->addSql(sprintf('
                UPDATE event
                SET external_id = REPLACE(external_id, \'%s\', \'\')
                WHERE external_origin = \'%s\' AND external_id LIKE \'%s%%\'
            ', $prefix, $origin, $prefix));

            $this->addSql(sprintf('
                UPDATE place_metadata
                SET external_id = REPLACE(external_id, \'%s\', \'\')
                WHERE external_origin = \'%s\' AND external_id LIKE \'%s%%\'
            ', $prefix, $origin, $prefix));
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
