<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
final class Version20210705183528 extends AbstractMigration
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
                SET external_id = REPLACE(external_id, \'%s\', \'\'),
                    external_origin = \'%s\'
                WHERE external_id LIKE \'%s%%\'
            ', $prefix, $origin, $prefix));

            $this->addSql(sprintf('
                INSERT INTO place_metadata (place_id, external_id, external_origin, external_updated_at)
                    SELECT p.id, REPLACE(p.external_id, \'%s\', \'\'), \'%s\', pd.last_updated
                    FROM place p
                    LEFT JOIN parser_data pd ON pd.external_id = p.external_id
                    WHERE p.external_id LIKE \'%s%%\'
            ', $prefix, $origin, $prefix));

            $this->addSql(sprintf('
                UPDATE place
                SET external_id = REPLACE(external_id, \'%s\', \'\')
                WHERE external_id LIKE \'%s%%\'
            ', $prefix, $prefix));
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
