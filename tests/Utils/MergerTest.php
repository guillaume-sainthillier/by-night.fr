<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Entity\Place;
use App\Tests\ContainerTestCase;
use App\Utils\Merger;

class MergerTest extends ContainerTestCase
{
    /**
     * @var Merger
     */
    protected $merger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = static::$container->get(Merger::class);
    }

    public function testPlaceMerge()
    {
        //Simple places
        $persistedPlace = (new Place())->setId(1)->setNom('Dynamo')->setVille('Toulouse')->setCodePostal('31000');
        $parsedPlace = (new Place())->setNom('La Dynamo')->setVille('Toulouse')->setCodePostal('31000')->setLatitude(43.6)->setUrl('https://www.google.com')->setFacebookId('FB ID');

        $this->merger->mergePlace($persistedPlace, $parsedPlace);

        $this->assertEquals($persistedPlace->getId(), 1);
        $this->assertEquals($persistedPlace->getNom(), 'Dynamo');
        $this->assertEquals($persistedPlace->getVille(), 'Toulouse');
        $this->assertEquals($persistedPlace->getCodePostal(), '31000');
        $this->assertEquals($persistedPlace->getLatitude(), 43.6);
        $this->assertEquals($persistedPlace->getUrl(), 'https://www.google.com');
        $this->assertEquals($persistedPlace->getFacebookId(), 'FB ID');
    }

    public function testEventMerge()
    {
        $persistedEvent = (new Event())->setId(1)->setNom('My Event')->setDescriptif('Event description');
        $parsedEvent = (new Event())->setId(2)->setNom('My Event V2')->setDescriptif('Event description V2');

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getId(), 1); //ID is intact
        $this->assertEquals($persistedEvent->getNom(), 'My Event V2'); //Newest field
        $this->assertEquals($persistedEvent->getDescriptif(), 'Event description V2'); //Newest field

        $databaseDate = new \DateTime('now');
        $parsedDate = new \DateTime('now');

        $persistedEvent = (new Event())->setDateDebut($databaseDate);
        $parsedEvent = (new Event())->setDateDebut($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getDateDebut(), $databaseDate); //DateTime have not changed (prevents ORM panic)

        $databaseDate = new \DateTime('now');
        $parsedDate = new \DateTime('tomorrow');

        $persistedEvent = (new Event())->setDateDebut($databaseDate);
        $parsedEvent = (new Event())->setDateDebut($parsedDate);

        $this->merger->mergeEvent($persistedEvent, $parsedEvent);
        $this->assertEquals($persistedEvent->getDateDebut(), $parsedDate); //DateTime have changed because it's not same day
    }
}
