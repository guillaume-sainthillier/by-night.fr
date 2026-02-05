<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

final readonly class Comparator
{
    public function __construct(private Util $util)
    {
    }

    public function sanitize(?string $string): string
    {
        return Monitor::bench(__METHOD__, fn () => $this->doSanitize($string));
    }

    public function sanitizeNumber(?string $string): ?string
    {
        return preg_replace('#\D#', '', (string) $string);
    }

    private function doSanitize(?string $string): string
    {
        if (null === $string || '' === trim($string)) {
            return '';
        }

        $string = $this->util->deleteStopWords($string);
        $string = $this->util->utf8LowerCase($string);
        $string = $this->util->replaceAccents($string);
        $string = $this->util->replaceNonAlphanumericChars($string);
        $string = $this->util->deleteStopWords($string);
        $string = $this->util->deleteMultipleSpaces($string);

        return trim((string) $string);
    }
}
