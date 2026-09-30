<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\AgendaTypeClassifier;
use App\Elasticsearch\Message\ClassifyEvents;
use App\Stats\UpcomingEventCounter;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Stores the agenda types of the events just indexed: an event imported or changed on the site shows on its type
 * links right away. The type counts of its city and country are recounted when it changed on the site, so its type
 * card counts it too; those of the imports wait for app:events:classify-agenda-types, run after the parsers.
 */
#[AsMessageHandler]
final readonly class ClassifyEventsHandler
{
    public function __construct(
        private AgendaTypeClassifier $classifier,
        private UpcomingEventCounter $counter,
    ) {
    }

    public function __invoke(ClassifyEvents $message): void
    {
        $changed = $this->classifier->classify($message->eventIds, new DateTimeImmutable('today'));
        if ($message->recount && [] !== $changed) {
            $this->counter->refreshAgendaTypesOfEvents($changed);
        }
    }
}
