<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * Who a publishers' news edition is for, among the owners of a publisher who did not opt out: the two never overlap, so
 * a member gets the monthly news while they come, and a single "what changed" once they stopped coming.
 */
enum PublisherNewsAudience
{
    /** How long without logging in makes a publisher dormant (a date modifier) */
    public const string DORMANT_AFTER = '6 months';

    /** Not logged in for DORMANT_AFTER */
    case Dormant;

    /** Logged in within DORMANT_AFTER */
    case Active;

    public function getLabel(): string
    {
        return match ($this) {
            self::Dormant => \sprintf('publishers not logged in for %s', self::DORMANT_AFTER),
            self::Active => \sprintf('publishers logged in within %s', self::DORMANT_AFTER),
        };
    }
}
