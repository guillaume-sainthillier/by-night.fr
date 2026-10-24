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

    /**
     * The dormant publishers last logged in before this date are left out: on the comeback's first run (2026-10-24), a
     * tenth of their addresses bounced against 1 in 27 of the later ones, which put the mail account at risk
     */
    public const string REACHABLE_SINCE = '2018-01-01';

    /**
     * The domains of the addresses made up for the members of the former Twitter and Facebook logins, who gave none:
     * no mail server answers them
     */
    public const array PLACEHOLDER_EMAIL_DOMAINS = ['twitter.fr', 'facebook.fr'];

    /** Not logged in for DORMANT_AFTER, but since REACHABLE_SINCE */
    case Dormant;

    /** Logged in within DORMANT_AFTER */
    case Active;

    public function getLabel(): string
    {
        return match ($this) {
            self::Dormant => \sprintf('publishers not logged in for %s, but since %s', self::DORMANT_AFTER, self::REACHABLE_SINCE),
            self::Active => \sprintf('publishers logged in within %s', self::DORMANT_AFTER),
        };
    }
}
