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
use App\Api\ApiResource\EventDetails;
use App\Api\Factory\EventOutputFactory;
use App\Api\Input\QueryInputMapper;
use App\Api\Model\EventLookupInput;
use App\Repository\EventRepository;
use Mcp\Schema\Result\CallToolResult;
use Psr\Clock\ClockInterface;

/**
 * An event online, for GET /api/events/{id} and the MCP tool get_event. A family's other members give its page, as on
 * the site.
 *
 * @implements ProviderInterface<EventDetails|CallToolResult>
 */
final readonly class EventDetailsProvider implements ProviderInterface
{
    public function __construct(
        private QueryInputMapper $inputMapper,
        private EventRepository $eventRepository,
        private EventOutputFactory $eventOutputFactory,
        private ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EventDetails|CallToolResult
    {
        $id = isset($uriVariables['id']) ? (int) $uriVariables['id'] : $this->inputMapper->map($operation, $context, EventLookupInput::class)->id;
        $event = $this->eventRepository->find($id);
        $event = $event?->getDuplicateOf() ?? $event;
        if (null === $event || $event->isDraft()) {
            return NotFound::answer(\sprintf('No event %d.', $id), $context);
        }

        return $this->eventOutputFactory->createDetails($event, $this->clock->now(), EventOutputFactory::utmOf($context, 'get_event'));
    }
}
