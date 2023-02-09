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
use App\Handler\EventHandler;
use App\Handler\ReservationsHandler;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SowProgParser extends AbstractParser
{
    /**
     * @var string
     */
    private const BASE_URI = 'https://agenda.sowprog.com';

    private readonly HttpClientInterface $client;

    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        ReservationsHandler $reservationsHandler,
        HttpClientInterface $client,
        string $sowprogUsername,
        string $sowprogPassword
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $reservationsHandler);

        $this->client = $client->withOptions([
            'base_uri' => self::BASE_URI,
            'auth_basic' => [$sowprogUsername, $sowprogPassword],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Sow Prog';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $modifiedSince = $incremental ? 1_000 * (time() - 86_400) : 0;
        $response = $this->client->request('GET', '/rest/v1_2/scheduledEvents?modifiedSince=' . $modifiedSince);
        $events = $response->toArray();

        foreach ($events['eventDescription'] as $eventAsArray) {
            foreach ($eventAsArray['eventSchedule']['eventScheduleDate'] as $scheduledEventAsArray) {
                $event = $this->arrayToDto($eventAsArray, $scheduledEventAsArray);
                if (null === $event) {
                    continue;
                }

                $this->publish($event);
            }
        }
    }

    private function arrayToDto(array $data, array $scheduleData): ?EventDto
    {
        if (!isset($data['location'])) {
            return null;
        }

        if (!isset($data['event'])) {
            return null;
        }

        if (!isset($data['location']['contact'])) {
            return null;
        }

        $locationData = $data['location'];
        $eventData = $data['event'];
        $contactData = $locationData['contact'];

        $hours = null;
        if ($scheduleData['startHour'] && $scheduleData['startHour'] !== $scheduleData['endHour']) {
            $hours = sprintf(
                'De %s à %s',
                str_replace(':', 'h', (string) $scheduleData['startHour']),
                str_replace(':', 'h', (string) $scheduleData['endHour'])
            );
        } elseif ($scheduleData['startHour']) {
            $hours = sprintf(
                'À %s',
                str_replace(':', 'h', (string) $scheduleData['startHour'])
            );
        }

        $description = null;
        foreach ($data['artist'] as $artist) {
            $description .= sprintf(
                "\n<h2>%s</h2>\n%s",
                $artist['name'],
                $artist['description']
            );
        }

        $prices = null;
        if (!empty($data['eventPrice'])) {
            $prices = array_map(static fn (array $price) => sprintf('%s : %d%s', $price['label'], $price['price'], 'EUR' === $price['currency'] ? '€' : $price['currency']), $data['eventPrice']);
            $prices = implode(' - ', $prices);
        }

        $websiteContacts = [];
        if (!empty($data['ticketStore'])) {
            $tickets = array_map(static fn (array $ticket) => $ticket['url'], $data['ticketStore']);
            $websiteContacts = $tickets;
        }

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->name = $eventData['title'];
        $event->description = $eventData['description'];
        $event->source = 'https://www.sowprog.com/';
        $event->externalId = sprintf('%s-%s', $data['id'], $scheduleData['id']);
        $event->imageUrl = $eventData['picture'] ?? $eventData['thumbnail'] ?? null;
        if ($event->imageUrl) {
            $event->imageUrl = str_replace('http://pro.sowprog.com/', 'https://pro.sowprog.com/', $event->imageUrl);
        }

        $event->externalUpdatedAt = (new DateTimeImmutable())->setTimestamp((int) round($data['modificationDate'] / 1_000));
        $event->type = $eventData['eventType']['label'];
        $event->category = $eventData['eventStyle']['label'];
        $event->startDate = new DateTimeImmutable($scheduleData['date']);
        $event->endDate = new DateTimeImmutable($scheduleData['endDate']);
        $event->hours = $hours;
        $event->websiteContacts = $websiteContacts;
        $event->prices = $prices;
        $event->latitude = (float) $contactData['lattitude'];
        $event->longitude = (float) $contactData['longitude'];

        $place = new PlaceDto();
        $place->name = $locationData['name'];
        $place->externalId = $locationData['id'];
        $place->street = trim(sprintf('%s %s', $contactData['addressLine1'], $contactData['addressLine2']));

        $city = new CityDto();
        $city->postalCode = $contactData['zipCode'];
        $city->name = $contactData['city'];

        $country = new CountryDto();
        $country->name = $contactData['country'];

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
        return 'sowprog';
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserVersion(): string
    {
        return '1.2';
    }
}
