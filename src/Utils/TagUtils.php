<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

final class TagUtils
{
    /**
     * @return string[]
     */
    public static function getTagTerms(string $tag): array
    {
        return array_values(array_filter(array_unique(array_map('trim', preg_split('#[,/]#', $tag)))));
    }
}
