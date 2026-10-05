<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Routing;

/**
 * The {location} of the URLs: a city or a country ("/toulouse", "/suisse"), or a city of a country that prefixes its
 * cities' URLs ("/suisse/geneve", see Country::$prefixesCities). The words that follow a location in the routes
 * ("/suisse/agenda", "/suisse/soiree/…", "/suisse/2") are never a city of it: CitySlugHandler keeps them free.
 */
final class LocationRequirement
{
    /** The second segments of a location's own routes */
    public const array RESERVED = ['agenda', 'soiree'];

    /** config/routes.yaml */
    public const string PATTERN = '[^/]+(?:/(?!(?:agenda|soiree|\d+)(?:/|$))[^/]+)?';

    /**
     * Whether a city of a prefixing country may end its slug with this segment.
     */
    public static function isReserved(string $segment): bool
    {
        return \in_array($segment, self::RESERVED, true) || ctype_digit($segment);
    }
}
