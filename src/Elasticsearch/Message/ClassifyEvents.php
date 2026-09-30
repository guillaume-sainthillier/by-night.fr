<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Message;

/**
 * Find the agenda types of events whose documents were just written (AgendaTypeClassifier::classify()).
 */
final readonly class ClassifyEvents
{
    /**
     * @param list<int> $eventIds
     */
    public function __construct(
        public array $eventIds,
    ) {
    }
}
