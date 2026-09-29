<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\City;
use App\Entity\Country;
use Symfony\Component\Asset\Packages;

/**
 * The picture of a city or country portal: the hero image uploaded in the back office, else the photo shipped with
 * the assets for the biggest cities, else none.
 */
final readonly class LocationPicture
{
    /** City slug => file name in assets/images/sites/ */
    private const array SITE_PHOTOS = [
        'basse-terre' => 'basse-tiers',
        'bordeaux' => 'bordeaux',
        'brest' => 'brest',
        'caen' => 'caen',
        'cayenne' => 'cayenne',
        'dijon' => 'dijon',
        'fort-de-france' => 'fort-de-france',
        'grenoble' => 'grenoble',
        'lille' => 'lille',
        'lyon' => 'lyon',
        'mamoudzou' => 'mamoudzou',
        'marseille' => 'marseille',
        'montpellier' => 'montpellier',
        'nantes' => 'nantes',
        'narbonne' => 'narbonne',
        'nice' => 'nice',
        'paris' => 'paris',
        'perpignan' => 'perpignan',
        'poitiers' => 'poitiers',
        'reims' => 'reims',
        'rennes' => 'rennes',
        'rouen' => 'rouen',
        'saint-denis' => 'saint-denis',
        'strasbourg' => 'strasbourg',
        'toulouse' => 'toulouse',
    ];

    public function __construct(
        private Packages $packages,
    ) {
    }

    /**
     * What <twig:Picasso:Image> needs to render the picture, null when there is none.
     *
     * @return array{loader: string|null, src: string|null, context: array{entity?: City|Country, field?: string}}|null
     */
    public function getPicture(City|Country $location): ?array
    {
        if ($location->hasHeroImage()) {
            return [
                'loader' => 'vich',
                'src' => null,
                'context' => ['entity' => $location, 'field' => 'heroImageFile'],
            ];
        }

        $photo = $location instanceof City ? (self::SITE_PHOTOS[$location->getSlug()] ?? null) : null;
        if (null === $photo) {
            return null;
        }

        return [
            'loader' => null,
            'src' => $this->packages->getUrl('build/images/sites/' . $photo . '.jpg', 'local'),
            'context' => [],
        ];
    }
}
