<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Entity\Place;
use App\Tests\AppKernelTestCase;
use App\Utils\Merger;
use DateTime;

class MergerTest extends AppKernelTestCase
{
    protected ?Merger $merger = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = static::getContainer()->get(Merger::class);
    }

    public function testPlaceMerge()
    {
        // Simple places
        $persistedPlace = (new Place())->setId(1)->setName('Dynamo')->setCityName('Toulouse')->setCityPostalCode('31000');
        $parsedPlace = (new Place())->setName('La Dynamo')->setCityName('Toulouse')->setCityPostalCode('31000')->setLatitude(43.6)->setUrl('https://www.google.com')->setFacebookId('FB ID');

        $this->merger->mergePlace($persistedPlace, $parsedPlace);

        self::assertEquals($persistedPlace->getId(), 1);
        self::assertEquals($persistedPlace->getName(), 'Dynamo');
        self::assertEquals($persistedPlace->getCityName(), 'Toulouse');
        self::assertEquals($persistedPlace->getCityPostalCode(), '31000');
        self::assertEquals($persistedPlace->getLatitude(), 43.6);
        self::assertEquals($persistedPlace->getUrl(), 'https://www.google.com');
        self::assertEquals($persistedPlace->getFacebookId(), 'FB ID');
    }

    public function testEventMerge()
    {
        $persistedEvent = (new Event())->setId(1)->setName('My Event')->setDescription('Event description');
        $parsedEvent = (new Event())->setId(2)->setName('My Event V2')->setDescription('Event description V2');

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        self::assertEquals($persistedEvent->getId(), 1); // ID is intact
        self::assertEquals($persistedEvent->getName(), 'My Event V2'); // Newest field
        self::assertEquals($persistedEvent->getDescription(), 'Event description V2'); // Newest field

        $databaseDate = new DateTime('now');
        $parsedDate = new DateTime('now');

        $persistedEvent = (new Event())->setStartDate($databaseDate);
        $parsedEvent = (new Event())->setStartDate($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        self::assertEquals($persistedEvent->getStartDate(), $databaseDate); // DateTime have not changed (prevents ORM panic)

        $databaseDate = new DateTime('now');
        $parsedDate = new DateTime('tomorrow');

        $persistedEvent = (new Event())->setStartDate($databaseDate);
        $parsedEvent = (new Event())->setStartDate($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        self::assertEquals($persistedEvent->getStartDate(), $parsedDate); // DateTime have changed because it's not same day
    }
}
