<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use Collator;

/**
 * A key equal for two strings exactly when MySQL's utf8mb4_unicode_ci collation holds them
 * equal: case and accents aside ("Théâtre" = "theatre"), "ß" = "ss", "œ" = "oe", trailing
 * spaces ignored. For in-memory lookups that must agree with a unique index on such a
 * column: a lookup that tells apart two names the index holds equal lets a duplicate reach
 * the INSERT, where the index rejects it.
 */
final class CollationKey
{
    private static ?Collator $collator = null;

    public static function of(string $value): string
    {
        // Built on UCA 4.0.0, utf8mb4_unicode_ci weighs every character beyond the Basic
        // Multilingual Plane (emoji among them) the same, as U+FFFD
        $value = (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", rtrim($value, ' '));

        return bin2hex((string) self::getCollator()->getSortKey($value));
    }

    private static function getCollator(): Collator
    {
        if (null === self::$collator) {
            // The primary strength compares base letters only, the way the "_ci" collations do
            self::$collator = new Collator('root');
            self::$collator->setStrength(Collator::PRIMARY);
        }

        return self::$collator;
    }
}
