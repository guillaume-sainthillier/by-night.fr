<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Parser\AbstractParser;
use DateTimeImmutable;

final class TestParser extends AbstractParser
{
    public static function getParserName(): string
    {
        return 'test';
    }

    public function parse(bool $incremental): void
    {
        $placeId = 'SP-9999999-' . random_int(0, 100000);
        $placeName = 'CECI ' . random_int(0, 50000) . 'EST UNIQUE - ' . random_int(0, 100000);
        for ($i = 1; $i <= 100; ++$i) {
            $eventDto = new EventDto();
            $eventDto->name = 'test';
            $eventDto->description = 'bonjour ça va';
            $eventDto->startDate = new DateTimeImmutable();
            $eventDto->endDate = new DateTimeImmutable();
            $eventDto->externalId = 'SP-99999-' . $i;
            $place = new PlaceDto();
            $place->externalId = $placeId;
            $place->name = $placeName;
            $city = new CityDto();
            $city->name = 'Marseille';
            $city->postalCode = '13005';

            $country = new CountryDto();
            $country->code = 'FR';

            $city->country = $country;
            $place->country = $country;
            $place->city = $city;

            $eventDto->place = $place;

            $this->publish($eventDto);
        }
    }

    public function getCommandName(): string
    {
        return 'test';
    }
}
