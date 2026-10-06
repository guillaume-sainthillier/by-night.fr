<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\Dto\CityDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Entity\Event;
use App\Entity\Place;
use App\SearchRepository\DuplicateEventFinder;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\EventNameMatcher;
use DateTimeImmutable;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use PHPUnit\Framework\TestCase;

final class DuplicateEventFinderTest extends TestCase
{
    /**
     * The events Elasticsearch returns.
     *
     * @var list<Event>
     */
    private array $candidates = [];

    /**
     * The queries sent to Elasticsearch.
     *
     * @var list<array<string, mixed>>
     */
    private array $queries = [];

    private DuplicateEventFinder $finder;

    protected function setUp(): void
    {
        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('find')->willReturnCallback(function (Query $query): array {
            $this->queries[] = $query->toArray();

            return $this->candidates;
        });

        $repositoryManager = $this->createStub(RepositoryManagerInterface::class);
        $repositoryManager->method('getRepository')->willReturn(new EventElasticaRepository($finder));

        $this->finder = new DuplicateEventFinder($repositoryManager, new EventNameMatcher());
    }

    /**
     * The events around on the same dates are kept when their names say the same event.
     */
    public function testTheEventsWhoseNamesSayTheSameEventAreKept(): void
    {
        $twin = self::event('Musicophotographie', "L'Arsenal, Salle De L'Esplanade", 49.1199, 6.1700);
        $this->candidates = [
            self::event('Mai à Vélo 2026', "L'Arsenal, Salle De L'Esplanade", 49.1199, 6.1700),
            $twin,
        ];

        $duplicates = $this->finder->find(self::dto('Musicophotographie – Projection photographique 2026', 'Arsenal', 49.1196, 6.1695));

        self::assertSame([$twin], $duplicates);
    }

    /**
     * Within a kilometre, the venue is the same: half of the distinctive words of the names is enough. Farther, one
     * word in common is too little.
     */
    public function testHalfTheWordsAreEnoughAtTheSameVenue(): void
    {
        $dto = self::dto('Les Cachottiers, une comédie de Luc Chaumar', 'Théâtre Le Bout', 48.8820, 2.3390);

        $this->candidates = [self::event('Les cachottiers', 'Théâtre Le Bout', 48.8825, 2.3395)];
        self::assertCount(1, $this->finder->find($dto));

        $this->candidates = [self::event('Les cachottiers', 'La Comédie de Paris', 48.8400, 2.3900)];
        self::assertSame([], $this->finder->find($dto), 'Five kilometres off');
    }

    public function testAtMostTheLimitIsKept(): void
    {
        $this->candidates = [
            self::event('Gervaise', 'Théâtre De La Violette', 43.6, 1.44),
            self::event('GERVAISE', 'Théâtre De La Violette', 43.6, 1.44),
        ];

        self::assertCount(1, $this->finder->find(self::dto('Gervaise', 'Théâtre De La Violette', 43.6, 1.44), 1));
    }

    public function testAnEventWithoutNameDatesOrPlaceIsNotSearched(): void
    {
        $dto = self::dto('', 'Arsenal', 49.1196, 6.1695);
        self::assertSame([], $this->finder->find($dto));

        $dto = self::dto('Musicophotographie', 'Arsenal', 49.1196, 6.1695);
        $dto->timesheets = [];
        self::assertSame([], $this->finder->find($dto));

        $dto = self::dto('Musicophotographie', 'Arsenal', null, null);
        $dto->place->city = null;
        self::assertSame([], $this->finder->find($dto), 'Nowhere to look around');

        self::assertSame([], $this->queries);
    }

    /**
     * Without dates of its own, the event's period is searched.
     */
    public function testTheEventPeriodIsSearchedWithoutDates(): void
    {
        $dto = self::dto('Musicophotographie', 'Arsenal', 49.1196, 6.1695);
        $dto->timesheets = [];
        $dto->startDate = new DateTimeImmutable('2026-05-16');
        $dto->endDate = new DateTimeImmutable('2026-05-20');

        $this->finder->find($dto);

        self::assertSame(
            [['bool' => ['filter' => [
                ['range' => ['sessions.endAt' => ['gte' => '2026-05-16']]],
                ['range' => ['sessions.startAt' => ['lte' => '2026-05-20']]],
            ]]]],
            $this->queries[0]['query']['bool']['filter'][0]['nested']['query']['bool']['should'],
        );
    }

    /**
     * Each date is a clause of the query: a weekly event over two years searches the period they span.
     */
    public function testManyDatesSearchThePeriodTheySpan(): void
    {
        $dto = self::dto('Brunch dominical', 'Le Comptoir', 43.6, 1.44);
        $dto->timesheets = [];
        for ($week = 0; $week < 60; ++$week) {
            $timesheet = new EventTimesheetDto();
            $timesheet->startAt = new DateTimeImmutable('2026-10-04')->modify(\sprintf('+%d weeks', $week));
            $timesheet->endAt = $timesheet->startAt;
            $dto->timesheets[] = $timesheet;
        }

        $this->finder->find($dto);

        self::assertSame(
            [['bool' => ['filter' => [
                ['range' => ['sessions.endAt' => ['gte' => '2026-10-04']]],
                ['range' => ['sessions.startAt' => ['lte' => '2027-11-21']]],
            ]]]],
            $this->queries[0]['query']['bool']['filter'][0]['nested']['query']['bool']['should'],
        );
    }

    /**
     * A draft about to be published is not its own duplicate.
     */
    public function testTheEventItselfIsLeftOut(): void
    {
        $dto = self::dto('Musicophotographie', 'Arsenal', 49.1196, 6.1695);
        $dto->entityId = 42;

        $this->finder->find($dto);

        self::assertEquals([['ids' => ['values' => ['42']]]], $this->queries[0]['query']['bool']['must_not']);
    }

    private static function dto(string $name, string $placeName, ?float $latitude, ?float $longitude): EventDto
    {
        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = new DateTimeImmutable('2026-05-16');
        $timesheet->endAt = new DateTimeImmutable('2026-05-16');

        $city = new CityDto();
        $city->name = 'Metz';
        $city->postalCode = '57000';

        $place = new PlaceDto();
        $place->name = $placeName;
        $place->latitude = $latitude;
        $place->longitude = $longitude;
        $place->city = $city;

        $dto = new EventDto();
        $dto->name = $name;
        $dto->timesheets = [$timesheet];
        $dto->place = $place;

        return $dto;
    }

    private static function event(string $name, string $placeName, float $latitude, float $longitude): Event
    {
        return new Event()
            ->setName($name)
            ->setPlace(new Place()->setName($placeName)->setCityName('Metz')->setLatitude($latitude)->setLongitude($longitude));
    }
}
