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
use App\Api\Model\EventActionOutput;
use App\Api\Model\EventDraftInput;
use App\Entity\Event;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<EventDraftInput, EventActionOutput>
 */
final readonly class EventDraftProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventActionOutput
    {
        $event = $context['request']->attributes->get('read_data');
        if (!$event instanceof Event) {
            throw new NotFoundHttpException('Not Found');
        }

        $event->setDraft($data->draft);
        $this->persistProcessor->process($event, $operation, $uriVariables, $context);

        return new EventActionOutput(success: true);
    }
}
