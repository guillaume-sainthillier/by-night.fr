<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005103752 extends AbstractMigration
{
    /**
     * [city id, its slug, its new slug] for each city holding the slug a country takes: countries and cities now
     * share the first segment of the URLs, and the country's comes first. Their events and venues redirect to the new
     * slug by themselves (EventRedirectManager, AgendaController). Listed from the production copy of 2026-10; the
     * regions of the same names (Guadeloupe, Guyane) have no page and keep theirs.
     */
    private const array RENAMES = [
        // A French locality of 104 inhabitants (1 venue, no event to come)
        [2973718, 'suisse', 'suisse-1'],
        // A locality of Martinique (1 venue, 2 events to come); martinique-1 and -2 are its region and department
        [6605750, 'martinique', 'martinique-3'],
    ];

    public function getDescription(): string
    {
        return 'Drop the "c--" prefix of the country slugs (/c--france is /france): countries and cities share the first segment of the URLs, the cities holding a country slug (suisse, martinique) take the next free one';
    }

    public function up(Schema $schema): void
    {
        $renamed = array_column(self::RENAMES, 0);
        $collisions = $this->connection->fetchFirstColumn(
            "SELECT z.slug FROM admin_zone z JOIN country c ON z.slug = SUBSTRING(c.slug, 4)
             WHERE z.type = 'PPL' AND c.slug LIKE 'c--%' AND z.id NOT IN (?)",
            [$renamed],
            [ArrayParameterType::INTEGER],
        );
        $this->abortIf([] !== $collisions, \sprintf('Cities hold country slugs this migration does not rename: %s', implode(', ', $collisions)));

        foreach (self::RENAMES as [$id, $slug, $newSlug]) {
            $this->addSql(
                'UPDATE admin_zone SET slug = :newSlug WHERE id = :id AND slug = :slug',
                ['id' => $id, 'slug' => $slug, 'newSlug' => $newSlug],
                ['id' => ParameterType::INTEGER],
            );
        }

        $this->addSql("UPDATE country SET slug = SUBSTRING(slug, 4) WHERE slug LIKE 'c--%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE country SET slug = CONCAT('c--', slug) WHERE slug NOT LIKE 'c--%'");

        foreach (self::RENAMES as [$id, $slug, $newSlug]) {
            $this->addSql(
                'UPDATE admin_zone SET slug = :slug WHERE id = :id AND slug = :newSlug',
                ['id' => $id, 'slug' => $slug, 'newSlug' => $newSlug],
                ['id' => ParameterType::INTEGER],
            );
        }
    }
}
