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
use App\Repository\CountryRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Parsedown;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAgendaParser extends AbstractParser
{
    /**
     * Attempts at the same page of the agenda list before the run gives up.
     */
    private const int MAX_ATTEMPTS = 3;

    private const int EVENT_BATCH_SIZE = 300;

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        private readonly HttpClientInterface $client,
        private readonly CountryRepository $countryRepository,
        #[Autowire(env: 'OPEN_AGENDA_KEY')]
        private readonly string $openAgendaKey,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
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
    protected function fetchEvents(?DateTimeImmutable $since): iterable
    {
        foreach ($this->getAgendasUidAndSlugs() as [$agendaId, $agendaSlug]) {
            foreach ($this->getAgendaEvents($since, $agendaId) as $event) {
                yield $this->arrayToDto($event, $agendaSlug);
            }
        }
    }

    private function getAgendaEvents(?DateTimeImmutable $since, int $agendaId): iterable
    {
        // A full import keeps the events not over yet. timings[gte] only matches a timing that
        // begins in the range, which drops an exhibition begun last week and running for a
        // month; "current" (a timing under way) and "upcoming" together are exactly the
        // events that have not ended.
        $filter = null !== $since
            ? ['updatedAt' => ['gte' => self::withSafetyMargin($since)->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM)]]
            : ['relative' => ['current', 'upcoming']];

        $after = [];
        while (true) {
            $response = $this->client->request('GET', \sprintf('https://api.openagenda.com/v2/agendas/%d/events/', $agendaId), [
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
        $failedAttempts = 0;
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
                $failedAttempts = 0;

                foreach ($data['agendas'] as $agenda) {
                    // One event not over yet, running (current) or to come (upcoming), is
                    // enough: a season announced weeks ahead has nothing running yet, and
                    // an agenda down to its last exhibition has nothing to come
                    $publishedEvents = $agenda['summary']['publishedEvents'] ?? [];
                    if (($publishedEvents['current'] ?? 0) + ($publishedEvents['upcoming'] ?? 0) > 0) {
                        yield [$agenda['uid'], $agenda['slug']];
                    }
                }

                if (empty($data['after'])) {
                    return;
                }

                $after = $data['after'];
            } catch (TransportExceptionInterface|HttpExceptionInterface $exception) {
                // A revoked key, a quota or an outage never goes away by asking again: past a few
                // attempts the run fails, and the next one starts over from the same watermark
                if (++$failedAttempts >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }

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
        // The feed does not always carry the clean ISO alpha-2 the docs promise: locations
        // created by bulk imports have shown up empty or lower-cased before OpenAgenda
        // normalises them. An unusable value must take the region/department fallback
        // rather than travel as a code that no Country row will ever match.
        $countryCode = self::normalizeCountryCode($location['countryCode'] ?? null);
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

        // Build timesheets from timings array
        $timings = $data['timings'];
        $timesheets = [];
        $allHours = [];

        foreach ($timings as $timing) {
            $timesheetDto = new EventTimesheetDto();
            $timesheetDto->startAt = new DateTimeImmutable($timing['begin']);
            $timesheetDto->endAt = new DateTimeImmutable($timing['end']);
            $timesheetDto->hours = self::hours($timesheetDto->startAt, $timesheetDto->endAt);

            $timesheets[] = $timesheetDto;
            $allHours[$timesheetDto->hours] = true;
        }

        // Compute aggregate start/end dates for backwards compatibility
        $firstTiming = reset($timings);
        $lastTiming = end($timings);
        $startDate = new DateTimeImmutable($firstTiming['begin']);
        $endDate = new DateTimeImmutable($lastTiming['end']);

        // One slot repeated over the dates summarises the event; distinct slots (a morning
        // and an afternoon session, say) cannot, the timesheets carry them.
        $hours = 1 === \count($allHours) ? array_key_first($allHours) : null;

        $mdParser = new Parsedown();
        // An empty long description ("") must fall back too: the check above lets it through
        $description = $mdParser->text(($data['longDescription'] ?? null) ?: $data['description']);

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

        $categoryLabel = $data['type-de-lieu-organisateur']['label'] ?? null;

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->name = $data['title'];
        $event->description = $description;
        $event->source = \sprintf(
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
        $event->timesheets = $timesheets;
        if (null !== $categoryLabel && '' !== trim($categoryLabel)) {
            $event->category = TagDto::fromString($categoryLabel);
        }

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

    /**
     * A timing is one slot with both ends, "De 09h00 à 12h30"; OpenAgenda repeats the
     * start as the end when the producer gave none, "À 09h00".
     */
    private static function hours(DateTimeInterface $begin, DateTimeInterface $end): string
    {
        $start = $begin->format('H\hi');
        $finish = $end->format('H\hi');

        return $start === $finish ? \sprintf('À %s', $start) : \sprintf('De %s à %s', $start, $finish);
    }

    /**
     * Upper-cased, trimmed ISO alpha-2 code, or null for anything else ("", "fr " is
     * fine, "FRA" or "France" is not).
     */
    private static function normalizeCountryCode(mixed $code): ?string
    {
        if (!\is_string($code)) {
            return null;
        }

        $code = strtoupper(trim($code));

        return 1 === preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }
}
