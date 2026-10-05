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
use App\Routing\LocationRequirement;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Sluggable\Handler\SlugHandlerInterface;
use Gedmo\Sluggable\Mapping\Event\SluggableAdapter;
use Gedmo\Sluggable\SluggableListener;

/**
 * The slug of a city, which names it in the URLs next to the countries ("/france", "/toulouse"):
 * - a city of a country that prefixes its cities' URLs (Country::$prefixesCities) starts with the country's slug
 *   ("suisse/geneve"), unique within its country, and never ends with a word of a location's routes
 *   ("suisse/agenda", see LocationRequirement);
 * - another city never takes a country's slug, which comes first.
 *
 * Gedmo only makes a slug unique among the zones: the slug that breaks a rule takes the next free suffix instead
 * ("suisse" is "suisse-1").
 */
final class CitySlugHandler implements SlugHandlerInterface
{
    private const string SEPARATOR = '/';

    /** @var array<string, true> the slugs this handler gave, maybe not flushed yet */
    private array $given = [];

    /** The country's slug of the city being slugged, when its country prefixes its cities */
    private ?string $prefix = null;

    /** @var callable|null */
    private $transliterator;

    public function __construct(private readonly SluggableListener $sluggable)
    {
    }

    public function onChangeDecision(SluggableAdapter $ea, array &$config, $object, &$slug, &$needToChangeSlug): void
    {
    }

    public function postSlugBuild(SluggableAdapter $ea, array &$config, $object, &$slug): void
    {
        $country = $object instanceof City ? $object->getCountry() : null;
        $this->prefix = null !== $country && $country->prefixesCities() ? $country->getSlug() : null;
        if (null === $this->prefix) {
            return;
        }

        // Gedmo urlizes the whole slug: the prefix comes in after, along with the urlized name (as RelativeSlugHandler)
        $this->transliterator = $this->sluggable->getTransliterator();
        $this->sluggable->setTransliterator($this->transliterate(...));
    }

    public function transliterate(string $text, string $separator, object $object): string
    {
        \assert(null !== $this->transliterator && null !== $this->prefix);
        $this->sluggable->setTransliterator($this->transliterator);
        $urlized = \call_user_func($this->sluggable->getUrlizer(), \call_user_func($this->transliterator, $text, $separator, $object), $separator, $object);

        return $this->prefix . self::SEPARATOR . $urlized;
    }

    public function onSlugCompletion(SluggableAdapter $ea, array &$config, $object, &$slug): void
    {
        $manager = $ea->getObjectManager();
        if (!$object instanceof City || !$manager instanceof EntityManagerInterface) {
            return;
        }

        $connection = $manager->getConnection();
        $breaksARule = null !== $this->prefix
            ? LocationRequirement::isReserved(substr($slug, \strlen($this->prefix) + 1))
            : false !== $connection->fetchOne('SELECT 1 FROM country WHERE slug = ?', [$slug]);
        if ($breaksARule) {
            $slug = self::freeSlug($connection, $slug, $this->given);
            $this->given[$slug] = true;
        }
    }

    public function handlesUrlization(): bool
    {
        return null !== $this->prefix;
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
