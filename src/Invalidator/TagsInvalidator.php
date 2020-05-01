<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Invalidator;

use App\App\Location;
use App\Entity\Calendrier;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use Exception;
use FOS\HttpCacheBundle\CacheManager;
use Psr\Log\LoggerInterface;

class TagsInvalidator
{
    private CacheManager $tagHandler;

    private LoggerInterface $logger;

    private bool $debug;

    private array $tags;

    public function __construct(CacheManager $tagHandler, LoggerInterface $logger, $debug)
    {
        $this->tagHandler = $tagHandler;
        $this->logger = $logger;
        $this->debug = $debug;
        $this->tags = [];
    }

    public static function getMenuTag()
    {
        return 'menu';
    }

    public function addCity(City $city)
    {
        $this->tags[] = 'autocomplete-city';
        $this->tags[] = self::getCityTag($city);
    }

    public static function getCityTag(City $city)
    {
        return \sprintf('city-%d', $city->getId());
    }

    public function addUser(User $user)
    {
        $this->tags[] = self::getUserTag($user);
    }

    public static function getUserTag(User $user)
    {
        return \sprintf('user-%d', $user->getId());
    }

    public function addCalendrier(Calendrier $calendrier)
    {
        $this->tags[] = self::getTendanceTag($calendrier->getEvent());
    }

    public static function getTendanceTag(Event $event)
    {
        return \sprintf('tendances-%d', $event->getId());
    }

    public function addEvent(Event $event)
    {
        if ($event->getId()) {
            $this->tags[] = self::getEventTag($event);
        }

        if ($event->getPlace() !== null) {
            $this->addPlace($event->getPlace());
        }
    }

    public static function getEventTag(Event $event)
    {
        return \sprintf('event-%d', $event->getId());
    }

    public static function getLocationTag(Location $location)
    {
        return \sprintf('location-%s', $location->getId());
    }

    public function addPlace(Place $place)
    {
        if ($place->getId()) {
            $this->tags[] = self::getPlaceTag($place);
        }

        $this->tags[] = self::getLocationTag($place->getLocation());
    }

    public static function getPlaceTag(Place $place)
    {
        return \sprintf('place-%d', $place->getId());
    }

    public function flush()
    {
        if ($this->debug) {
            unset($this->tags); //Call GC
            $this->tags = [];

            return;
        }

        $tags = \array_filter(\array_unique($this->tags));

        if (\count($tags) === 0) {
            return;
        }

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage(), [
                'exception' => $e,
            ]);
        }

        unset($this->tags); //Call GC
        $this->tags = [];
    }
}
