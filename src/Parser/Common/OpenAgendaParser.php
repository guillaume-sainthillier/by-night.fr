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
use App\Repository\CountryRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Parsedown;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAgendaParser extends AbstractParser
{
    /**
     * @var int
     */
    private const EVENT_BATCH_SIZE = 300;

    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        ReservationsHandler $reservationsHandler,
        private readonly HttpClientInterface $client,
        private readonly CountryRepository $countryRepository,
        private readonly string $openAgendaKey,
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $reservationsHandler);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Open Agenda';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $agendasUidAndSlugs = $this->getAgendasUidAndSlugs();

        foreach ($agendasUidAndSlugs as $agendasUidAndSlug) {
            $this->fetchAgendaEvents($incremental, [$agendasUidAndSlug]);
        }
    }

    private function fetchAgendaEvents(bool $incremental, iterable $agendaIdAndSlugs): void
    {
        foreach ($agendaIdAndSlugs as $agendaIdAndSlug) {
            [$agendaId, $agendaSlug] = $agendaIdAndSlug;
            $events = $this->getAgendaEvents($incremental, $agendaId);
            foreach ($events as $event) {
                $eventDto = $this->arrayToDto($event, $agendaSlug);
                if (null === $eventDto) {
                    continue;
                }

                $this->publish($eventDto);
            }
        }
    }

    private function getAgendaEvents(bool $incremental, int $agendaId): iterable
    {
        $filter = $incremental
            ? ['updatedAt' => ['gte' => (new DateTimeImmutable('yesterday', new DateTimeZone('UTC')))->setTime(0, 0)->format(DateTimeInterface::ATOM)]]
            : ['timings' => ['gte' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTime(0, 0)->format(DateTimeInterface::ATOM)]];

        $after = [];
        while (true) {
            $response = $this->client->request('GET', sprintf('https://api.openagenda.com/v2/agendas/%d/events/', $agendaId), [
                'query' => array_merge($filter, [
                    'key' => $this->openAgendaKey,
                    'includeLabels' => true,
                    'detailed' => true,
                    'monolingual' => 'fr',
                    'size' => self::EVENT_BATCH_SIZE,
                    'after' => $after,
                ]),
            ]);

            $data = $response->toArray();

            foreach ($data['events'] as $event) {
                yield $event;
            }

            if (empty($data['after']) || \count($data['events']) < self::EVENT_BATCH_SIZE) {
                return;
            }

            $after = $data['after'];
        }
    }

    private function getAgendasUidAndSlugs(): iterable
    {
        $after = [];
        while (true) {
            try {
                $response = $this->client->request('GET', 'https://api.openagenda.com/v2/agendas', [
                    'query' => [
                        'key' => $this->openAgendaKey,
                        'size' => 100,
                        'after' => $after,
                        'fields' => ['summary'],
                    ],
                ]);

                $data = $response->toArray();

                foreach ($data['agendas'] as $agenda) {
                    $summary = $agenda['summary'];
                    if (
                        isset($summary['publishedEvents']['current'])
                        && isset($summary['publishedEvents']['upcoming'])
                        && 0 !== $summary['publishedEvents']['current']
                        && 0 !== $summary['publishedEvents']['upcoming']
                    ) {
                        yield [$agenda['uid'], $agenda['slug']];
                    }
                }

                if (empty($data['after'])) {
                    return;
                }

                $after = $data['after'];
            } catch (TransportExceptionInterface|HttpExceptionInterface $exception) {
                $this->logException($exception);
            }
        }
    }

    private function arrayToDto(array $data, string $agendaSlug): ?EventDto
    {
        if (empty($data['longDescription']) && empty($data['description'])) {
            return null;
        }

        if (empty($data['location'])) {
            return null;
        }

        if (empty($data['timings'])) {
            return null;
        }

        $location = $data['location'];
        $countryCode = $location['countryCode'];
        if (
            null === $countryCode
            && (
                !empty($location['adminLevel1'])
                || !empty($location['adminLevel2'])
            )
        ) {
            $country = $this->countryRepository->getFromRegionOrDepartment($location['adminLevel1'] ?? null, $location['adminLevel2'] ?? null);
            $countryCode = $country?->getId();
        }

        if (null === $countryCode) {
            return null;
        }

        $timings = $data['timings'];
        $startDate = current($timings);
        $endDate = end($timings);
        $startDate = new DateTimeImmutable($startDate['begin']);
        $endDate = new DateTimeImmutable($endDate['end']);

        $hours = null;
        if ($startDate->format('Y-m-d') !== $endDate->format('Y-m-d')) {
            $hours = sprintf('De %s à %s', $startDate->format("H\hi"), $endDate->format("H\hi"));
        } else {
            $hours = sprintf('A %s', $startDate->format("H\hi"));
        }

        $mdParser = new Parsedown();
        $description = $mdParser->text($data['longDescription'] ?? $data['description']);

        $type = $data['keywords'] ?? [];

        $imageUrl = null;
        foreach ($data['image']['variants'] ?? [] as $variant) {
            if ('full' === $variant['type']) {
                $imageUrl = $variant['filename'];
            }
        }

        $imageUrl ??= $data['image']['filename'] ?? null;
        if (null !== $imageUrl) {
            $imageUrl = $data['image']['base'] . $imageUrl;
        }

        $urls = [];
        foreach ($data['registration'] ?? [] as $registration) {
            if ('link' !== $registration['type']) {
                continue;
            }

            $urls[] = $registration['value'];
        }

        if (!empty($location['website'])) {
            $urls[] = $location['website'];
        }

        $phones = [];
        if (!empty($location['phone'])) {
            $phones[] = $location['phone'];
        }

        $emails = [];
        if (!empty($location['email'])) {
            $emails[] = $location['email'];
        }

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->name = $data['title'];
        $event->description = $description;
        $event->source = sprintf(
            'https://openagenda.com/%s/events/%s',
            $agendaSlug,
            $data['slug'],
        );
        $event->externalId = $data['uid'];
        $event->imageUrl = $imageUrl;
        $event->externalUpdatedAt = new DateTimeImmutable($data['updatedAt']);
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->prices = $data['conditions'] ?? null;
        $event->latitude = $location['latitude'];
        $event->longitude = $location['longitude'];
        $event->address = $location['address'];
        $event->type = implode(',', $type);
        $event->websiteContacts = $urls;
        $event->phoneContacts = $phones;
        $event->emailContacts = $emails;

        $place = new PlaceDto();
        $place->name = $location['name'] ?? null;
        $place->externalId = $location['uid'];
        $place->latitude = $location['latitude'];
        $place->longitude = $location['longitude'];

        $city = new CityDto();
        $city->postalCode = $location['postalCode'] ?? null;
        $city->name = $location['city'] ?? null;

        $country = new CountryDto();
        $country->code = $countryCode;

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
        return 'openagenda';
    }
}
