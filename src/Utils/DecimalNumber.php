<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

/**
 * A number as the sources write it, with a decimal comma or a decimal point: "27,50" and "22.0000" alike. PHP's own
 * casts read the point only, and stop at the comma: (float) "27,50" is 27.0.
 */
final class DecimalNumber
{
    /**
     * @param string $number digits with at most one decimal separator, as a pattern matched them ("27,50", "39")
     */
    public static function parse(string $number): float
    {
        return (float) str_replace(',', '.', $number);
    }
}
