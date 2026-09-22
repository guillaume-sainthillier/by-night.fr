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

final class Version20260921132913 extends AbstractMigration
{
    /**
     * Tables that carry real data: a duplicate key there has to be resolved before this
     * migration can be applied: app:places:merge-duplicates --apply for places,
     * app:events:merge-duplicates --strategy=exact for events and
     * app:tags:merge-duplicates --apply for tags. Redirect stubs left by the events
     * command carry no external identity, which the checks and the keys allow.
     */
    private const array DUPLICATE_KEY_CHECKS = [
        'place_metadata' => 'SELECT external_id, external_origin, COUNT(*) AS c FROM place_metadata GROUP BY external_id, external_origin HAVING c > 1',
        'event' => 'SELECT external_id, external_origin, COUNT(*) AS c FROM `event` WHERE external_id IS NOT NULL AND external_origin IS NOT NULL GROUP BY external_id, external_origin HAVING c > 1',
        'tag' => 'SELECT name, COUNT(*) AS c FROM tag GROUP BY name HAVING c > 1',
    ];

    public function getDescription(): string
    {
        return 'Unique keys on the import identity columns (explorations, place metadata, events, tags) so concurrent parser workers cannot create duplicates';
    }

    public function preUp(Schema $schema): void
    {
        foreach (self::DUPLICATE_KEY_CHECKS as $table => $sql) {
            $duplicates = $this->connection->fetchAllAssociative($sql);
            if ([] === $duplicates) {
                continue;
            }

            $this->write(\sprintf('%d duplicate key(s) in "%s", first one: %s', \count($duplicates), $table, json_encode($duplicates[0])));
            $this->abortIf(true, \sprintf('Resolve the duplicate keys in "%s" before applying this migration. List them with: %s', $table, $sql));
        }
    }

    public function up(Schema $schema): void
    {
        // Explorations are a cache of the last observation per key: collapse duplicates by keeping the newest row
        $this->addSql('DELETE older FROM parser_data older INNER JOIN parser_data newer ON newer.external_id = older.external_id AND newer.external_origin = older.external_origin AND newer.id > older.id');

        // Create each unique key before dropping the plain index it replaces, so lookups stay indexed throughout
        $this->addSql('CREATE UNIQUE INDEX parser_data_external_id_unique ON parser_data (external_id, external_origin)');
        $this->addSql('DROP INDEX parser_data_idx ON parser_data');
        $this->addSql('CREATE UNIQUE INDEX place_metadata_external_id_unique ON place_metadata (external_id, external_origin)');
        $this->addSql('DROP INDEX place_metadata_idx ON place_metadata');
        $this->addSql('CREATE UNIQUE INDEX event_external_id_unique ON `event` (external_id, external_origin)');
        $this->addSql('DROP INDEX event_external_id_idx ON `event`');
        $this->addSql('CREATE UNIQUE INDEX tag_name_unique ON tag (name)');
        $this->addSql('DROP INDEX tag_name_idx ON tag');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX parser_data_idx ON parser_data (external_id, external_origin)');
        $this->addSql('DROP INDEX parser_data_external_id_unique ON parser_data');
        $this->addSql('CREATE INDEX place_metadata_idx ON place_metadata (external_id, external_origin)');
        $this->addSql('DROP INDEX place_metadata_external_id_unique ON place_metadata');
        $this->addSql('CREATE INDEX event_external_id_idx ON `event` (external_id, external_origin)');
        $this->addSql('DROP INDEX event_external_id_unique ON `event`');
        $this->addSql('CREATE INDEX tag_name_idx ON tag (name)');
        $this->addSql('DROP INDEX tag_name_unique ON tag');
    }
}
