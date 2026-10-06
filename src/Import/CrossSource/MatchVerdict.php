<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

/**
 * Whether two events of two sources are the same show, or why not. The reasons a pair is turned down are kept apart
 * so the report tells which rule holds it back.
 */
enum MatchVerdict: string
{
    /** One of them is not an imported event a source still lists, or both come from the same source */
    case NotComparable = 'not_comparable';

    /** Not at the same venue */
    case OtherVenue = 'other_venue';

    /** No session of one falls on a day of the other */
    case OtherDay = 'other_day';

    /** The same words, once the noise the sources add is gone */
    case Same = 'same';

    /** One title holds the other and the shorter one is specific enough to name a show on its own */
    case Contained = 'contained';

    /** Nothing distinctive left ("Visite guidée", "Concert"): no evidence of sameness */
    case Generic = 'generic';

    /** One is another product than the show: a parking, a pass, a VIP package */
    case OtherProduct = 'other_product';

    /** One is a tribute ("Hommage à Queen") and the other is not */
    case Tribute = 'tribute';

    /** They name other numbers: another day of a festival, another part of a series */
    case OtherNumber = 'other_number';

    case Different = 'different';

    public function isMatch(): bool
    {
        return self::Same === $this || self::Contained === $this;
    }
}
