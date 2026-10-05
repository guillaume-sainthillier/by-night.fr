<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Exception;

use App\Entity\City;
use Exception;

/**
 * The URL names a city by a former slug (CityLegacySlug): MovedCitySubscriber redirects to the same page under its
 * current one. Not a RuntimeException: AppContextSubscriber turns those into a 404.
 */
final class CityMovedException extends Exception
{
    public function __construct(private readonly City $city)
    {
        parent::__construct(\sprintf('The city is now "%s"', $city->getSlug()));
    }

    public function getCity(): City
    {
        return $this->city;
    }
}
