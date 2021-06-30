<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

class ChunkUtils
{
    /**
     * @param object[] $objects
     *
     * @return object[][]
     */
    public static function getChunksByClass(array $objects): array
    {
        $chunks = [];
        foreach ($objects as $i => $object) {
            $key = \get_class($object);
            $chunks[$key][$i] = $object;
        }

        return $chunks;
    }

    /**
     * @param object[] $objects
     *
     * @return object[][][]
     */
    public static function getNestedChunksByClass(array $objects, int $chunkSize): array
    {
        $chunks = self::getChunksByClass($objects);

        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = array_chunk($chunk, $chunkSize, true);
        }

        return $chunks;
    }
}
