<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Digest;

use App\Digest\WeekendDigestSelector;
use App\Entity\Event;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Repository\EventRepository;
use App\Search\DateRange;
use App\SearchRepository\EventElasticaRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Elastica\Index;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

/**
 * The events of a weekend selection, once Elasticsearch found those of the weekend in the city and around.
 */
final class WeekendDigestSelectorTest extends AppKernelTestCase
{
    public function testThePicturesComeFirstThenTheMostFollowedThenTheAgendaOrder(): void
    {
        $plain = $this->event('No picture, followed', ['participations' => 90]);
        $first = $this->event('Picture, first in the agenda', ['image' => $this->picture()]);
        $followed = $this->event('Picture, followed', ['participations' => 12, 'image' => $this->picture()]);
        $fetched = $this->event('Fetched picture', ['imageSystem' => $this->picture()]);

        $selection = $this->selector([$plain, $first, $fetched, $followed])->select(CityFactory::toulouse()->create(), $this->weekend());

        self::assertSame(
            ['Picture, followed', 'Picture, first in the agenda', 'Fetched picture', 'No picture, followed'],
            array_map(static fn (Event $event): ?string => $event->getName(), $selection),
        );
    }

    public function testTheSelectionStopsAtItsLimitAndLeavesOutWhatTheIndexStillHolds(): void
    {
        $events = [
            $this->event('Draft', ['draft' => true, 'image' => $this->picture()]),
            $this->event('One'),
            $this->event('Two'),
            $this->event('Three'),
        ];

        $selection = $this->selector($events)->select(CityFactory::toulouse()->create(), $this->weekend(), 2);

        self::assertSame(['One', 'Two'], array_map(static fn (Event $event): ?string => $event->getName(), $selection));
    }

    public function testNothingFoundIsAnEmptySelection(): void
    {
        self::assertSame([], $this->selector([])->select(CityFactory::toulouse()->create(), $this->weekend()));
    }

    /**
     * @param list<Event> $found what Elasticsearch finds, in the order of the agenda
     */
    private function selector(array $found): WeekendDigestSelector
    {
        $results = array_map(static fn (Event $event): Result => new Result(['_id' => (string) $event->getId()]), $found);
        $index = $this->createStub(Index::class);
        $index->method('search')->willReturn(new ResultSet(new Response([]), new Query(), $results));

        $manager = $this->createStub(RepositoryManagerInterface::class);
        $manager->method('getRepository')->willReturn(new EventElasticaRepository($this->createStub(PaginatedFinderInterface::class)));

        return new WeekendDigestSelector($index, $manager, self::getContainer()->get(EventRepository::class));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function event(string $name, array $attributes = []): Event
    {
        return EventFactory::new()->withDates(new DateTimeImmutable('2026-10-24'))->create(['name' => $name] + $attributes);
    }

    private function picture(): EmbeddedFile
    {
        $picture = new EmbeddedFile();
        $picture->setName('poster.jpg');

        return $picture;
    }

    private function weekend(): DateRange
    {
        return new DateRange(new DateTimeImmutable('2026-10-23'), new DateTimeImmutable('2026-10-25'));
    }
}
