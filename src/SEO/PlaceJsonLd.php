<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\Country;
use App\Entity\Place;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The venue of an agenda page: what search engines need to tie its events to one place.
 */
final readonly class PlaceJsonLd
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function generatePlaceJsonLd(Place $place): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            'name' => $place->getName(),
            'url' => $this->urlGenerator->generate('app_agenda_by_place', [
                'placeSlug' => $place->getSlug(),
                'location' => $place->getLocationSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $address = array_filter([
            'streetAddress' => $place->getStreet(),
            'addressLocality' => $place->getCityName(),
            'postalCode' => $place->getCityPostalCode(),
            'addressCountry' => $place->getCountry() instanceof Country ? $place->getCountry()->getId() : null,
        ]);
        if ([] !== $address) {
            $schema['address'] = ['@type' => 'PostalAddress', ...$address];
        }

        if ($place->getLatitude() && $place->getLongitude()) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $place->getLatitude(),
                'longitude' => $place->getLongitude(),
            ];
        }

        return json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
