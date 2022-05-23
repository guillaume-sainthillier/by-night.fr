<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Entity\Place;
use App\Tests\ContainerTestCase;
use App\Utils\Merger;
use DateTime;

class MergerTest extends ContainerTestCase
{
    protected ?Merger $merger = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = self::$container->get(Merger::class);
    }

    public function testPlaceMerge()
    {
        // Simple places
        $persistedPlace = (new Place())->setId(1)->setName('Dynamo')->setCityName('Toulouse')->setCityPostalCode('31000');
        $parsedPlace = (new Place())->setName('La Dynamo')->setCityName('Toulouse')->setCityPostalCode('31000')->setLatitude(43.6)->setUrl('https://www.google.com')->setFacebookId('FB ID');

        $this->merger->mergePlace($persistedPlace, $parsedPlace);

        $this->assertEquals($persistedPlace->getId(), 1);
        $this->assertEquals($persistedPlace->getName(), 'Dynamo');
        $this->assertEquals($persistedPlace->getCityName(), 'Toulouse');
        $this->assertEquals($persistedPlace->getCityPostalCode(), '31000');
        $this->assertEquals($persistedPlace->getLatitude(), 43.6);
        $this->assertEquals($persistedPlace->getUrl(), 'https://www.google.com');
        $this->assertEquals($persistedPlace->getFacebookId(), 'FB ID');
    }

    public function testEventMerge()
    {
        $persistedEvent = (new Event())->setId(1)->setName('My Event')->setDescriptif('Event description');
        $parsedEvent = (new Event())->setId(2)->setName('My Event V2')->setDescriptif('Event description V2');

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getId(), 1); // ID is intact
        $this->assertEquals($persistedEvent->getName(), 'My Event V2'); // Newest field
        $this->assertEquals($persistedEvent->getDescriptif(), 'Event description V2'); // Newest field

        $databaseDate = new DateTime('now');
        $parsedDate = new DateTime('now');

        $persistedEvent = (new Event())->setStartDate($databaseDate);
        $parsedEvent = (new Event())->setStartDate($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getStartDate(), $databaseDate); // DateTime have not changed (prevents ORM panic)

        $databaseDate = new DateTime('now');
        $parsedDate = new DateTime('tomorrow');

        $persistedEvent = (new Event())->setStartDate($databaseDate);
        $parsedEvent = (new Event())->setStartDate($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getStartDate(), $parsedDate); // DateTime have changed because it's not same day
    }
}
