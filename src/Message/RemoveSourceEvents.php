<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

/**
 * Flags the events a parser's source no longer lists (see RemovedEventDto), dispatched on the
 * "parser" transport so that the import worker stays the only writer of imported events.
 */
final readonly class RemoveSourceEvents
{
    /**
     * @param list<string> $externalIds
     */
    public function __construct(
        public string $externalOrigin,
        public array $externalIds,
        public ?string $sourcePrefix = null,
    ) {
    }
}
