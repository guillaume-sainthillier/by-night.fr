<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

/**
 * What a PATCH of an event changes: whether it is a draft (off the agenda) and whether it is cancelled. A field left
 * out is left as it is.
 */
final readonly class EventStateInput
{
    public function __construct(
        public ?bool $draft = null,
        public ?bool $cancelled = null,
    ) {
    }
}
