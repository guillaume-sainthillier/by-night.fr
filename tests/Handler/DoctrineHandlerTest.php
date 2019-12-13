<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Entity\Place;
use App\Handler\DoctrineEventHandler;
use App\Reject\Reject;
use App\Tests\ContainerTestCase;

class DoctrineHandlerTest extends ContainerTestCase
{
    /**
     * @var DoctrineEventHandler
     */
    protected $doctrineHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHandler = static::$container->get(DoctrineEventHandler::class);
    }

    private function makeAsserts(Place $place, ?string $countryCode, ?string $cityName, ?string $postalCode, int $rejectReason)
    {
        $message = 'Original : ' . ($place->getNom() ?: ($place->getVille() ?: $place->getCodePostal()));
        if (null !== $countryCode) {
            $this->assertNotNull($place->getCountry(), $message . '. Expected country : ' . $countryCode);
            $this->assertEquals($countryCode, $place->getCountry()->getId(), $message);
        } else {
            $this->assertNull($place->getCountry(), $message);
        }

        if (null !== $cityName) {
            $this->assertNotNull($place->getCity(), $message . '. Expected city : ' . $cityName);
            $this->assertEquals($cityName, $place->getCity()->getName(), $message);
        } else {
            $this->assertNull($place->getCity(), $message);
        }

        if (null !== $postalCode) {
            $this->assertNotNull($place->getZipCity(), $message . '. Expected zip city : ' . $postalCode);
            $this->assertEquals($postalCode, $place->getZipCity()->getPostalCode(), $message);
        } else {
            $this->assertNull($place->getZipCity(), $message);
        }

        $this->assertNotNull($place->getReject(), $message);
        $this->assertEquals($rejectReason, $place->getReject()->getReason(), $message);
    }

    /**
     * @dataProvider guessEventLocationProvider()
     */
    public function testGuessEventLocation(Place $place, ?string $countryCode, ?string $cityName, ?string $postalCode, int $rejectReason)
    {
        $place->setReject(new Reject());
        $this->doctrineHandler->guessEventLocation($place);

        $this->makeAsserts($place, $countryCode, $cityName, $postalCode, $rejectReason);
    }

    public function guessEventLocationProvider()
    {
        return [
            // Pas de pays
            [
                (new Place())->setCodePostal('99999')->setVille('LoremIpsum'),
                null,
                null,
                null,
                Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
            ],
            // Mauvais CP + mauvaise ville + mauvais pays
            [
                (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setCountryName('LoremIpsum'),
                null,
                null,
                null,
                Reject::BAD_COUNTRY | Reject::VALID,
            ],
            // Mauvais CP + mauvaise ville + mauvais pays
            [
                (new Place())->setCountryName('LoremIpsum'),
                null,
                null,
                null,
                Reject::BAD_COUNTRY | Reject::VALID,
            ],
            // Mauvais CP + mauvaise ville + bon pays
            [
                (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setCountryName('France'),
                'FR',
                null,
                null,
                Reject::VALID,
            ],
            // Mauvais CP + bonne ville + bon pays
            [
                (new Place())->setCodePostal('99999')->setVille('St Germain En Laye')->setCountryName('France'),
                'FR',
                'Saint-Germain-en-Laye',
                null,
                Reject::VALID,
            ],
            // Mauvais CP + ville doublon + bon pays
            [
                (new Place())->setCodePostal('31000')->setVille('Roques')->setCountryName('France'),
                'FR',
                'Toulouse',
                '31000',
                Reject::VALID,
            ],
            // CP doublon + pas de ville + bon pays
            [
                (new Place())->setCodePostal('31470')->setCountryName('France'),
                'FR',
                null,
                null,
                Reject::VALID,
            ],
            // Pas de CP + ville doublon + bon pays
            [
                (new Place())->setVille('Roques')->setCountryName('France'),
                'FR',
                null,
                null,
                Reject::VALID,
            ],
            // Pas de CP + bonne ville + bon pays
            [
                (new Place())->setVille('toulouse')->setCountryName('France'),
                'FR',
                'Toulouse',
                null,
                Reject::VALID,
            ],
            // Monaco
            [
                (new Place())->setNom('Centre Hospitalier Princesse Grace')->setRue('1 Avenue Pasteur')->setCodePostal('98000')->setVille('Monaco')->setCountryName('Monaco'),
                'MC',
                'Monaco',
                '98000',
                Reject::VALID,
            ],
            // Bonnes coordonnées + mauvais pays
            [
                (new Place())->setNom('10, Av Princesse Grace')->setLongitude(7.4314023071828)->setLatitude(43.743460394373),
                null,
                null,
                null,
                Reject::NO_COUNTRY_PROVIDED | Reject::VALID,
            ],
        ];
    }
}
