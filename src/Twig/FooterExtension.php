<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Entity\Country;
use App\Repository\CityRepository;
use Twig\Attribute\AsTwigFunction;

final class FooterExtension
{
    public function __construct(
        private readonly CityRepository $cityRepository,
    ) {
    }

    /**
     * @return array<string>
     */
    #[AsTwigFunction(name: 'random_cities')]
    public function randomCities(?Country $country = null): array
    {
        return $this->cityRepository->findAllRandomNames($country);
    }
}
