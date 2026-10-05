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
 * @deprecated one tag per message: replaced by PurgeCdnCacheTags, kept to read the messages already queued
 */
final readonly class PurgeCdnCacheTag
{
    public function __construct(public string $tag)
    {
    }
}
