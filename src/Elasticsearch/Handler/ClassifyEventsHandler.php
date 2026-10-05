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
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Stores the agenda types of the events just indexed: an event imported or changed on the site shows on its type
 * pages, and counts on the type links and cards of the agenda, right away.
 */
#[AsMessageHandler]
final readonly class ClassifyEventsHandler
{
    public function __construct(
        private AgendaTypeClassifier $classifier,
    ) {
    }

    public function __invoke(ClassifyEvents $message): void
    {
        $this->classifier->classify($message->eventIds, new DateTimeImmutable('today'));
    }
}
