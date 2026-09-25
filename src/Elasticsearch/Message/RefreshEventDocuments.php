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
 * Re-index the events whose document copies what changed: the places and tags they point
 * to, or their own sessions.
 */
final readonly class RefreshEventDocuments
{
    /**
     * @param list<int> $placeIds
     * @param list<int> $tagIds
     * @param list<int> $eventIds
     */
    public function __construct(
        public array $placeIds = [],
        public array $tagIds = [],
        public array $eventIds = [],
    ) {
    }
}
