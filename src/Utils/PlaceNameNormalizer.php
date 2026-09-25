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
 * The name is sanitized (stop words removed, accents folded, non-alphanumeric
 * characters dropped, lower-cased and whitespace-collapsed), then loses its city,
 * sanitized the same way: a place name often repeats it ("Zénith de Toulouse",
 * "Salle d'Albi"). The city goes as a whole word, with the "de", "à" or "d'" that
 * ties it to the name.
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
        $normalized = self::sanitize($name);

        $city = null === $cityName ? '' : self::sanitize($cityName);
        if ('' !== $city) {
            // "d'" is glued to the city once the apostrophe is dropped ("salle dalbi"), and
            // only stands before a vowel, where French elides "de"
            $preposition = 1 === preg_match('/^[aeiouyh]/', $city) ? '(?:de |a |d ?)?' : '(?:de |a )?';
            $normalized = trim((string) preg_replace('/\b' . $preposition . preg_quote($city, '/') . '\b/', ' ', $normalized));
            $normalized = (string) preg_replace('/ {2,}/', ' ', $normalized);
        }

        return '' === $normalized ? null : $normalized;
    }

    private static function sanitize(string $text): string
    {
        return trim(new StringManipulator($text)
            ->deleteStopWords()
            ->replaceAccents()
            ->nonAlphanumericChars()
            ->lowerCase()
            ->deleteMultipleSpaces()
            ->toString());
    }
}
