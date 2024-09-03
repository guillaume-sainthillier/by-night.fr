<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Toulouse;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Parser\AbstractParser;
use DateTime;

final class BikiniParser extends AbstractParser
{
    /**
     * @var string
     */
    private const EVENTS_URL = 'https://lebikini.com/events.json';

    public static function getParserName(): string
    {
        return 'Bikini';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        // Récupère les différents liens à parser depuis le flux RSS
        $data = json_decode(file_get_contents(self::EVENTS_URL), true, 512, \JSON_THROW_ON_ERROR);

        foreach ($data['events'] as $eventAsArray) {
            $dto = $this->arrayToDto($eventAsArray);
            $this->publish($dto);
        }
    }

    private function arrayToDto(array $data): object
    {
        $startDate = DateTime::createFromFormat('U', $data['startTime']);
        $endDate = DateTime::createFromFormat('U', $data['endTime']);

        if ($startDate->getTimestamp() === $endDate->getTimestamp()) {
            $hours = \sprintf('À %s', $startDate->format('H:i'));
        } else {
            $hours = \sprintf('De %s à %s', $startDate->format('H:i'), $endDate->format('H:i'));
        }

        $placeParts = explode("\n", (string) $data['place']['address']);
        $placeParts = array_map('trim', $placeParts);

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['id'];
        $event->name = $data['title'];
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->description = $data['htmlDescription'];
        $event->type = 'Concert, Musique';
        $event->category = $data['style'];
        $event->source = $data['url'];
        $event->websiteContacts = [$data['ticketUrl']];
        $event->imageUrl = $data['image'];
        $event->status = 'postponed' === $data['status'] ? 'REPORTE' : null;
        $event->prices = implode(' // ', $data['prices']);

        $place = new PlaceDto();
        $place->name = $data['place']['name'];

        $city = new CityDto();

        if (\count($placeParts) >= 2) {
            if (preg_match("#^(\d+) (.+)$#", end($placeParts), $postalCodeAndCity)) {
                $city->postalCode = $postalCodeAndCity[1];
                $city->name = $postalCodeAndCity[2];
            }

            $place->street = 3 === \count($placeParts) ? $placeParts[1] : $placeParts[0];
        }

        $country = new CountryDto();
        $country->code = 'FR';

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'toulouse.bikini';
    }
}
