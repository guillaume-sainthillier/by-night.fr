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
 * Tells a member of the new events they follow (App\Follow\FollowAlertSender), on the "async" transport: those online
 * by the time it was scheduled, which a message delayed by retries keeps.
 */
final readonly class SendFollowAlert
{
    public function __construct(
        public int $userId,
        public DateTimeImmutable $until,
    ) {
    }
}
