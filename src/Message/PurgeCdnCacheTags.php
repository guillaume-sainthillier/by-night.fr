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
 * Cache-Tag values to purge from Cloudflare (EventPageCache), at most CloudflareCdnPurger::MAX_FILES_PER_REQUEST:
 * one message per flush instead of one per tag.
 */
final readonly class PurgeCdnCacheTags
{
    /**
     * @param non-empty-list<string> $tags
     */
    public function __construct(public array $tags)
    {
    }
}
