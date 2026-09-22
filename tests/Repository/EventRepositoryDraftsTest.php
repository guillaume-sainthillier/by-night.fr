<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\App\Location;
use App\Entity\Event;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;

/**
 * An unpublished event only shows in its owner's personal space.
 */
final class EventRepositoryDraftsTest extends AppKernelTestCase
{
    public function testThePublicListingsLeaveDraftsOut(): void
    {
        $city = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]);
        $today = new DateTimeImmutable('today');
        $published = EventFactory::new()->withDates($today)->create(['place' => $place, 'name' => 'Publié']);
        $draft = EventFactory::new()->withDates($today)->create(['place' => $place, 'name' => 'Brouillon', 'draft' => true]);
        $other = EventFactory::new()->withDates($today)->create(['place' => $place]);
        $member = UserFactory::createOne();
        UserEventFactory::createOne(['user' => $member, 'event' => $published, 'going' => true]);
        UserEventFactory::createOne(['user' => $member, 'event' => $draft, 'going' => true]);

        $repository = self::getContainer()->get(EventRepository::class);
        $location = new Location()->setCity($city);

        self::assertNotContains($draft->getId(), $this->ids($repository->findUpcomingEvents($location)));
        self::assertNotContains($draft->getId(), $this->ids($repository->findTopEventsQueryBuilder($location)));
        self::assertNotContains($draft->getId(), $this->ids($repository->findAllNextQueryBuilder($other)));
        self::assertNotContains($draft->getId(), $this->ids($repository->findAllSimilarsQueryBuilder($other)));
        self::assertSame([$published->getId()], $this->ids($repository->findAllNextEvents($member)));
        self::assertContains($published->getId(), $this->ids($repository->findUpcomingEvents($location)));
    }

    /**
     * @return list<int>
     */
    private function ids(QueryBuilder $queryBuilder): array
    {
        return array_map(static fn (Event $event): int => (int) $event->getId(), $queryBuilder->getQuery()->getResult());
    }
}
