<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

/**
 * A record its source says is gone (a tombstone), yielded by a parser's fetchEvents() next to
 * the events it reads: the event imported from it is flagged EventStatus::Removed.
 */
final readonly class RemovedEventDto
{
    /**
     * @param string|null $sourcePrefix only the event whose source URL starts with it: an OpenAgenda
     *                                  event shared by several agendas is removed from one of them, and
     *                                  only counts as gone from the agenda it was imported from
     */
    public function __construct(
        public string $externalId,
        public ?string $sourcePrefix = null,
    ) {
    }
}
