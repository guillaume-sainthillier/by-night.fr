<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

final class DownloadEventImages
{
    /**
     * The events whose page purge waits for their image (EventImageDownloadScheduler::defersPagePurge()). Declared
     * with a default so that a message queued before it existed unserializes with none.
     *
     * @var int[]
     */
    public array $pageEventIds = [];

    /**
     * @param int[] $eventIds
     * @param int[] $pageEventIds
     */
    public function __construct(
        public readonly array $eventIds,
        array $pageEventIds = [],
    ) {
        $this->pageEventIds = $pageEventIds;
    }
}
