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
use App\Entity\EventTimesheet;
use App\Utils\SessionHours;

/**
 * A session of an event: its day, its last one when it lasts several, its hours as its page writes them.
 */
final readonly class EventSession
{
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'Its day, YYYY-MM-DD'])]
        public string $date,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'Its last day, YYYY-MM-DD, for a session over several days'])]
        public ?string $endDate = null,
        /** As the page writes them: "20h", "de 14h à 18h" */
        public ?string $hours = null,
    ) {
    }

    public static function of(EventTimesheet $session): ?self
    {
        $start = $session->getStartAt()?->format('Y-m-d');
        if (null === $start) {
            return null;
        }

        $end = $session->getEndAt()?->format('Y-m-d');

        return new self($start, null !== $end && $end !== $start ? $end : null, SessionHours::ofSession($session));
    }
}
