<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

class MemoryUtils
{
    public static function getMemoryUsage(bool $realUsage = false): string
    {
        return self::formatMemory(memory_get_usage($realUsage));
    }

    public static function getPeakMemoryUsage(bool $realUsage = false): string
    {
        return self::formatMemory(memory_get_peak_usage($realUsage));
    }

    private static function formatMemory(int $size): string
    {
        $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $unit = (int) floor(log($size, 1024));

        return @round($size / 1024 ** $unit, 2) . ' ' . $units[$unit];
    }
}
