<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Entity\Country;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Application context service that holds the current location.
 * Acts as a centralized holder for location state that can be accessed globally.
 */
final class AppContext implements ResetInterface
{
    private ?Location $location = null;

    public function __construct(private readonly CityManager $cityManager)
    {
    }

    public function reset(): void
    {
        $this->location = null;
    }

    /**
     * Get the current location (from URL or cookie fallback).
     */
    public function getLocation(): ?Location
    {
        return $this->location;
    }

    /**
     * Set the current location and sync with CityManager.
     */
    public function setLocation(Location $location): void
    {
        $this->location = $location;

        // Sync with CityManager to maintain cookie behavior
        if ($city = $location->getCity()) {
            $this->cityManager->setCurrentCity($city);
        }
    }

    /**
     * Get the city from the current location.
     */
    public function getCity(): ?City
    {
        return $this->location?->getCity();
    }

    /**
     * Get the country from the current location.
     */
    public function getCountry(): ?Country
    {
        return $this->location?->getCountry();
    }

    /**
     * Get the slug from the current location.
     */
    public function getSlug(): ?string
    {
        return $this->location?->getSlug();
    }

    /**
     * Check if the current location is a city.
     */
    public function isCity(): bool
    {
        return $this->location?->isCity() ?? false;
    }

    /**
     * Check if the current location is a country.
     */
    public function isCountry(): bool
    {
        return $this->location?->isCountry() ?? false;
    }
}
