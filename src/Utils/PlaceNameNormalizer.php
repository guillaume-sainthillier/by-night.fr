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
 * Produces the canonical, comparable form of a place name.
 *
 * This MUST stay the single source of truth shared by:
 *  - PlaceComparator (fuzzy name matching), and
 *  - the indexed PlaceNameSlug (exact-match fast path).
 *
 * The city name is stripped first (a place name often repeats its city), then
 * the remainder is sanitized: stop words removed, accents folded, non-alphanumeric
 * characters dropped, lower-cased and whitespace-collapsed.
 */
final class PlaceNameNormalizer
{
    public function normalize(?string $name, ?string $cityName = null): ?string
    {
        if (null === $name) {
            return null;
        }

        if (null !== $cityName && '' !== trim($cityName)) {
            $name = str_ireplace($cityName, '', $name);
        }

        $normalized = trim(new StringManipulator($name)
            ->deleteStopWords()
            ->replaceAccents()
            ->nonAlphanumericChars()
            ->lowerCase()
            ->deleteMultipleSpaces()
            ->toString());

        return '' === $normalized ? null : $normalized;
    }
}
