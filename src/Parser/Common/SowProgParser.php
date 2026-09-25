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
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SowProgParser extends AbstractParser
{
    private const string BASE_URI = 'https://agenda.sowprog.com';

    private readonly HttpClientInterface $client;

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        HttpClientInterface $client,
        #[Autowire(env: 'SOWPROG_USER')]
        string $sowprogUsername,
        #[Autowire(env: 'SOWPROG_PASSWORD')]
        string $sowprogPassword,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);

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
    protected function fetchEvents(?DateTimeImmutable $since): iterable
    {
        $modifiedSince = null === $since ? 0 : 1_000 * self::withSafetyMargin($since)->getTimestamp();
        $response = $this->client->request('GET', '/rest/v1_2/scheduledEvents?modifiedSince=' . $modifiedSince);
        $events = $response->toArray();

        foreach ($events['eventDescription'] as $eventAsArray) {
            yield $this->mapRecord(fn (): ?EventDto => $this->arrayToDto($eventAsArray), ['id' => $eventAsArray['id'] ?? null]);
        }
    }

    private function arrayToDto(array $data): ?EventDto
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

        $scheduleDates = $data['eventSchedule']['eventScheduleDate'] ?? [];
        if (empty($scheduleDates)) {
            return null;
        }

        $locationData = $data['location'];
        $eventData = $data['event'];
        $contactData = $locationData['contact'];

        // Build timesheets from all schedule dates
        $timesheets = [];
        $allHours = [];

        foreach ($scheduleDates as $scheduleData) {
            $timesheetDto = new EventTimesheetDto();
            $timesheetDto->startAt = new DateTimeImmutable($scheduleData['date']);
            $timesheetDto->endAt = new DateTimeImmutable($scheduleData['endDate']);

            // Generate hours string for this timing
            if ($scheduleData['startHour'] && $scheduleData['startHour'] !== $scheduleData['endHour']) {
                $timesheetDto->hours = \sprintf(
                    'De %s à %s',
                    str_replace(':', 'h', (string) $scheduleData['startHour']),
                    str_replace(':', 'h', (string) $scheduleData['endHour'])
                );
            } elseif ($scheduleData['startHour']) {
                $timesheetDto->hours = \sprintf(
                    'À %s',
                    str_replace(':', 'h', (string) $scheduleData['startHour'])
                );
            }

            $timesheets[] = $timesheetDto;
            if ($timesheetDto->hours) {
                $allHours[$timesheetDto->hours] = true;
            }
        }

        // The schedules come in no particular order: the event spans from the earliest to the latest
        $startDate = min(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->startAt, $timesheets));
        $endDate = max(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->endAt, $timesheets));

        // Use first unique hour for aggregate display
        $hours = \count($allHours) > 1 ? null : array_key_first($allHours);

        $description = null;
        foreach ($data['artist'] as $artist) {
            $description .= \sprintf(
                "\n<h2>%s</h2>\n%s",
                $artist['name'],
                $artist['description']
            );
        }

        $prices = null;
        if (!empty($data['eventPrice'])) {
            $prices = array_map(static fn (array $price) => \sprintf(
                '%s : %s%s',
                // Some labels end with their own colon ("Tarif concert à 21h :")
                preg_replace('/[\s:]+$/u', '', (string) $price['label']),
                // As the feed gives it, cents included (12.5, not 12)
                (string) (float) $price['price'],
                'EUR' === $price['currency'] ? '€' : $price['currency']
            ), $data['eventPrice']);
            $prices = array_unique($prices);
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
        $event->externalId = (string) $data['id'];
        $event->imageUrl = $eventData['picture'] ?? $eventData['thumbnail'] ?? null;
        if ($event->imageUrl) {
            $event->imageUrl = str_replace('http://pro.sowprog.com/', 'https://pro.sowprog.com/', $event->imageUrl);
        }

        $event->externalUpdatedAt = new DateTimeImmutable()->setTimestamp((int) round($data['modificationDate'] / 1_000));
        $event->type = $eventData['eventType']['label'];

        $categoryLabel = $eventData['eventStyle']['label'] ?? null;
        if (null !== $categoryLabel && '' !== trim($categoryLabel)) {
            $event->category = TagDto::fromString($categoryLabel);
        }

        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->timesheets = $timesheets;
        $event->websiteContacts = $websiteContacts;
        $event->prices = $prices;
        $event->latitude = (float) $contactData['lattitude'];
        $event->longitude = (float) $contactData['longitude'];

        $place = new PlaceDto();
        $place->name = $locationData['name'];
        $place->externalId = $locationData['id'];
        $place->street = trim(\sprintf('%s %s', $contactData['addressLine1'], $contactData['addressLine2']));

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
    #[Override]
    public static function getParserVersion(): string
    {
        return '3.0';
    }
}
