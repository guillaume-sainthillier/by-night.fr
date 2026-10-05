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

final class Version20261005105142 extends AbstractMigration
{
    /** The countries whose cities' URLs start with their own: their names clash with the French ones ("geneve-1") */
    private const array PREFIXING_COUNTRIES = ['CH', 'BE', 'MC'];

    /** The second segments of a location's own routes (App\Routing\LocationRequirement, as of this migration) */
    private const array RESERVED = ['agenda', 'soiree'];

    private const int ROWS_PER_INSERT = 1000;

    public function getDescription(): string
    {
        return 'Put the cities of Switzerland, Belgium and Monaco under their country ("geneve-1" is "suisse/geneve"), unique within it; their former slugs go to city_legacy_slug, which redirects them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE city_legacy_slug (slug VARCHAR(200) NOT NULL, id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, INDEX city_legacy_slug_slug_idx (slug), INDEX IDX_94993E758BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE city_legacy_slug ADD CONSTRAINT FK_94993E758BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE country ADD prefixes_cities TINYINT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE country SET prefixes_cities = 1 WHERE id IN ('" . implode("', '", self::PREFIXING_COUNTRIES) . "')");

        $moves = $this->moves();
        if ([] === $moves) {
            return;
        }

        // The new slugs all hold a "/", which no slug held: no unique key clashes while they are written
        $this->addSql('CREATE TEMPORARY TABLE city_slug_move (id INT NOT NULL, slug VARCHAR(200) NOT NULL, former_slug VARCHAR(200) NOT NULL, PRIMARY KEY (id))');
        foreach (array_chunk($moves, self::ROWS_PER_INSERT) as $chunk) {
            $this->addSql(
                'INSERT INTO city_slug_move (id, slug, former_slug) VALUES ' . implode(', ', array_fill(0, \count($chunk), '(?, ?, ?)')),
                array_merge(...array_map(static fn (array $move): array => [$move['id'], $move['slug'], $move['former_slug']], $chunk)),
            );
        }

        $this->addSql('INSERT INTO city_legacy_slug (city_id, slug) SELECT id, former_slug FROM city_slug_move');
        $this->addSql('UPDATE admin_zone z JOIN city_slug_move m ON m.id = z.id SET z.slug = m.slug');
        $this->addSql('DROP TEMPORARY TABLE city_slug_move');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE admin_zone z JOIN city_legacy_slug l ON l.city_id = z.id SET z.slug = l.slug WHERE z.slug LIKE '%/%'");
        $this->addSql('ALTER TABLE country DROP prefixes_cities');
        $this->addSql('DROP TABLE city_legacy_slug');
    }

    /**
     * Each city of the prefixing countries, the most populated first: "<country>/<its slug without Gedmo's numbering>"
     * ("geneve-1" is "suisse/geneve"), numbered again within its country only. Its slug is kept rather than computed
     * again from its name: the slugger of the import that gave it differs ("abbaye-dorval", now "abbaye-d-orval").
     *
     * @return list<array{id: int, slug: string, former_slug: string}>
     */
    private function moves(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT z.id, z.slug, c.slug AS country_slug FROM admin_zone z JOIN country c ON c.id = z.country_id
             WHERE z.type = 'PPL' AND z.country_id IN ('" . implode("', '", self::PREFIXING_COUNTRIES) . "')
             ORDER BY z.country_id, z.population DESC, z.id",
        );

        $moves = [];
        $taken = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $name = preg_replace('/-\d+$/', '', $slug) ?: $slug;
            $base = $row['country_slug'] . '/' . $name;
            $candidate = $base;
            // A word of a location's routes ("suisse/agenda") is numbered like a namesake
            $reserved = \in_array($name, self::RESERVED, true) || ctype_digit($name);
            for ($n = 1; isset($taken[$candidate]) || ($reserved && $candidate === $base); ++$n) {
                $candidate = $base . '-' . $n;
            }

            $taken[$candidate] = true;
            $moves[] = ['id' => (int) $row['id'], 'slug' => $candidate, 'former_slug' => $slug];
        }

        return $moves;
    }
}
