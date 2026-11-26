<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\EventStateInput;
use App\Api\Model\EventStateOutput;
use App\Entity\Event;
use App\Enum\EventStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Puts an event online or back to a draft, cancels it or takes the cancellation back (the switches of the organizer's
 * list, the "Publier" button of a draft's preview).
 *
 * @implements ProcessorInterface<EventStateInput, EventStateOutput>
 */
final readonly class EventStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventStateOutput
    {
        $event = $context['request']->attributes->get('read_data');
        if (!$event instanceof Event) {
            throw new NotFoundHttpException('Not Found');
        }

        if (null !== $data->draft) {
            $event->setDraft($data->draft);
        }

        // Taking a cancellation back leaves another status alone (postponed, sold out…)
        if (true === $data->cancelled) {
            $event->setStatus(EventStatus::Cancelled);
        } elseif (false === $data->cancelled && EventStatus::Cancelled === $event->getStatus()) {
            $event->setStatus(null);
        }

        $this->persistProcessor->process($event, $operation, $uriVariables, $context);

        return new EventStateOutput(
            draft: $event->isDraft(),
            cancelled: EventStatus::Cancelled === $event->getStatus(),
        );
    }
}
