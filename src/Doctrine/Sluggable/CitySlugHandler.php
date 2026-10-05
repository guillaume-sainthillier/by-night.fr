<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\Sluggable;

use App\Entity\City;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Sluggable\Handler\SlugHandlerInterface;
use Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use Gedmo\Sluggable\SluggableListener;

/**
 * A city never takes the slug of a country: both name the first segment of the URLs ("/france", "/toulouse") and the
 * country wins (CountrySlugs). Gedmo only makes a slug unique among the zones; a city whose slug turns out to be a
 * country's takes the next free suffix instead ("suisse" is "suisse-1").
 */
final class CitySlugHandler implements SlugHandlerInterface
{
    /** @var array<string, true> the slugs this handler gave, maybe not flushed yet */
    private array $given = [];

    public function __construct(SluggableListener $sluggable)
    {
    }

    public function onChangeDecision(SluggableAdapter $ea, array &$config, $object, &$slug, &$needToChangeSlug): void
    {
    }

    public function postSlugBuild(SluggableAdapter $ea, array &$config, $object, &$slug): void
    {
    }

    public function onSlugCompletion(SluggableAdapter $ea, array &$config, $object, &$slug): void
    {
        $manager = $ea->getObjectManager();
        if (!$object instanceof City || !$manager instanceof EntityManagerInterface) {
            return;
        }

        $connection = $manager->getConnection();
        if (false !== $connection->fetchOne('SELECT 1 FROM country WHERE slug = ?', [$slug])) {
            $slug = self::freeSlug($connection, $slug, $this->given);
            $this->given[$slug] = true;
        }
    }

    public function handlesUrlization(): bool
    {
        return false;
    }

    public static function validate(array $options, ClassMetadata $meta): void
    {
    }

    /**
     * The first "<slug>-<n>" no zone has, the way Gedmo numbers its own.
     *
     * @param array<string, true> $given the slugs already given, maybe not flushed yet
     */
    public static function freeSlug(Connection $connection, string $slug, array $given = []): string
    {
        $taken = array_flip($connection->fetchFirstColumn('SELECT slug FROM admin_zone WHERE slug LIKE ?', [$slug . '-%']));
        $n = 1;
        $candidate = $slug . '-' . $n;
        while (isset($taken[$candidate]) || isset($given[$candidate])) {
            $candidate = $slug . '-' . ++$n;
        }

        return $candidate;
    }
}
