<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\ApiResource\EventSearchResult;
use App\Api\Factory\EventOutputFactory;
use App\Api\Input\QueryInputMapper;
use App\Api\Model\EventSearchInput;
use App\App\LazyLocationFactory;
use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Search\SearchEvent;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Mcp\Schema\Result\CallToolResult;
use Pagerfanta\Pagerfanta;

/**
 * The events of a city, searched as its agenda searches them (SearchEvent), for GET /api/events and the MCP tool
 * search_events.
 *
 * @implements ProviderInterface<EventSearchResult|CallToolResult>
 */
final readonly class EventSearchProvider implements ProviderInterface
{
    public function __construct(
        private QueryInputMapper $inputMapper,
        private RepositoryManagerInterface $repositoryManager,
        private LazyLocationFactory $locationFactory,
        private EventRepository $eventRepository,
        private EventOutputFactory $eventOutputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EventSearchResult|CallToolResult
    {
        $input = $this->inputMapper->map($operation, $context, EventSearchInput::class);
        $location = $this->findLocation($input->city);
        if (null === $location) {
            return NotFound::answer(\sprintf('No city "%s": find_cities lists the cities a name matches.', $input->city), $context);
        }

        $search = new SearchEvent()
            ->setLocation($location)
            ->setWhen($input->when)
            ->setFrom($input->from)
            ->setTo($input->to)
            ->setType($input->category)
            ->setPrice($input->price)
            ->setTerm(null === $input->query || '' === trim($input->query) ? null : trim($input->query));

        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);
        $pager = new Pagerfanta($repository->findWithSearch($search))->setMaxPerPage($input->limit);
        /** @var list<Event> $events */
        $events = iterator_to_array($pager->getCurrentPageResults(), false);
        $this->eventRepository->loadAllEager($events, ['view' => 'events:agenda:list']);
        $range = $search->getDateRange();
        $utm = EventOutputFactory::utmOf($context, 'search_events');

        return new EventSearchResult(
            city: $location->getCity()?->getName(),
            from: $range->from->format('Y-m-d'),
            to: $range->to?->format('Y-m-d'),
            total: $pager->getNbResults(),
            events: array_map(fn (Event $event) => $this->eventOutputFactory->createSummary($event, $range->from, $range->to, $utm), $events),
        );
    }

    /**
     * The city of a slug, else of a name the search finds.
     */
    private function findLocation(string $city): ?Location
    {
        $location = $this->locationFactory->createWithCity(strtolower(trim($city)));
        if (null !== $location) {
            return $location;
        }

        /** @var CityElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(City::class);
        foreach ($repository->findWithSearch($city)->setMaxPerPage(1)->getCurrentPageResults() as $found) {
            return new Location()->setCity($found);
        }

        return null;
    }
}
