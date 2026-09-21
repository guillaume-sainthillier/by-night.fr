<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Contracts\BatchResetInterface;

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
final class PlaceNameNormalizer implements BatchResetInterface
{
    /**
     * normalize() runs a regex with a few hundred stop-word alternatives (~9 µs per
     * call) and the fuzzy place fallback invokes it for every DTO × candidate pair:
     * up to ~100k pairs per import chunk for only ~2k distinct inputs. Results are
     * memoized for the duration of a batch: the cache is emptied with the other
     * per-batch state whenever the EntityManager is cleared (BatchResetInterface).
     * The cap is only a safety net for callers that never clear, and simply restarts
     * the cache when reached.
     */
    private const int CACHE_MAX_ENTRIES = 10_000;

    /** @var array<string, string|null> */
    private array $cache = [];

    public function normalize(?string $name, ?string $cityName = null): ?string
    {
        if (null === $name) {
            return null;
        }

        $key = $name . "\0" . ($cityName ?? '');
        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (\count($this->cache) >= self::CACHE_MAX_ENTRIES) {
            $this->cache = [];
        }

        return $this->cache[$key] = $this->doNormalize($name, $cityName);
    }

    public function batchReset(): void
    {
        $this->cache = [];
    }

    private function doNormalize(string $name, ?string $cityName): ?string
    {
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
