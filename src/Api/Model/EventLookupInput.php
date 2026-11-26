<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the MCP tool get_event takes, the id GET /api/events/{id} has in its path.
 */
final readonly class EventLookupInput
{
    public function __construct(
        /** The id of the event, as search_events gives it. */
        #[ApiProperty(required: true)]
        #[Assert\Positive]
        public int $id,
    ) {
    }
}
