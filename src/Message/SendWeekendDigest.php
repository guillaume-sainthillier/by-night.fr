<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

use DateTimeImmutable;

/**
 * Sends a member the weekend selection of their city (App\Digest\WeekendDigestSender), on the "async" transport. The
 * weekend is the one of the day it was scheduled, which a message delayed by retries keeps.
 */
final readonly class SendWeekendDigest
{
    public function __construct(
        public int $userId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
    }
}
