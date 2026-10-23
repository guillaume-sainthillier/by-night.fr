<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Digest;

use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Search\DateRange;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use Elastica\Index;
use Elastica\Result;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The events of the weekend selection: what the agenda of the city lists for the weekend (the city and around, see
 * EventElasticaRepository::createSearchQuery()), the ones with a picture and the most followed first.
 */
final readonly class WeekendDigestSelector
{
    /** The events of an e-mail */
    public const int EVENTS = 8;

    /**
     * The events of the weekend ranked, in the order of the agenda: a big city lists more than that on a weekend, but
     * the events nobody follows that come after are not the ones to put forward.
     */
    private const int CANDIDATES = 500;

    public function __construct(
        #[Autowire(service: 'fos_elastica.index.event')]
        private Index $eventIndex,
        private RepositoryManagerInterface $repositoryManager,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * @return list<Event>
     */
    public function select(City $city, DateRange $weekend, int $limit = self::EVENTS): array
    {
        $search = new SearchEvent()
            ->setLocation(new Location()->setCity($city))
            ->setFrom($weekend->from)
            ->setTo($weekend->to);

        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);
        $query = $repository->createSearchQuery($search)->setSize(self::CANDIDATES);
        $ids = array_map(static fn (Result $result): int => (int) $result->getId(), $this->eventIndex->search($query)->getResults());

        return $this->eventRepository->findMostFollowedAmong($ids, $limit);
    }
}
