<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use JsonException;

/**
 * Decodes JSON that may hold a lone UTF-16 surrogate escape ("\ud83d" without its "\ude00", an
 * emoji cut in half by a truncated description): json_decode() rejects the whole document over it,
 * and no JSON_INVALID_UTF8_* flag helps since those only cover raw bytes, not escapes.
 */
final class LenientJson
{
    /**
     * Every escape sequence, left to right, so that "\\ud83d" (an escaped backslash, then text)
     * is never read as an escape: a surrogate pair first, then a lone surrogate, then any other.
     */
    private const string ESCAPE_PATTERN = '/\\\\(?:u[dD][89abAB][0-9a-fA-F]{2}\\\\u[dD][c-fC-F][0-9a-fA-F]{2}|(u[dD][89a-fA-F][0-9a-fA-F]{2})|.)/s';

    /**
     * @return array<mixed>
     *
     * @throws JsonException when the document is not valid JSON for another reason, or not an array
     */
    public static function decode(string $json): array
    {
        $data = json_decode(self::replaceLoneSurrogates($json), true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new JsonException(\sprintf('JSON content was expected to decode to an array, "%s" returned.', get_debug_type($data)));
        }

        return $data;
    }

    public static function replaceLoneSurrogates(string $json): string
    {
        // Most documents have no surrogate escape at all
        if (!preg_match('/\\\\u[dD][89a-fA-F]/', $json)) {
            return $json;
        }

        return (string) preg_replace_callback(
            self::ESCAPE_PATTERN,
            static fn (array $matches): string => isset($matches[1]) ? '�' : $matches[0],
            $json,
        );
    }
}
