<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

final readonly class EventParticipationOutput
{
    public function __construct(
        public bool $success,
        public bool $like,
        public int $likes,
    ) {
    }
}
