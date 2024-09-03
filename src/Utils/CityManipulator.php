<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

final class CityManipulator
{
    public function getCityNameAlternatives(string $city): array
    {
        $city = $this->sanitizeCityName($city);

        $alternatives = [];

        $variants = [
            $city,
            preg_replace("#(^|\s)st(\s)#i", '$1saint$2', $city),
        ];

        foreach ($variants as $variant) {
            $alternatives[] = $variant;
            $alternatives[] = str_replace(' ', '-', (string) $variant);
            $alternatives[] = str_replace('-', ' ', (string) $variant);
            $alternatives[] = str_replace(["l'", "d'"], '', (string) $variant);
            $alternatives[] = str_replace("'", '', (string) $variant);
        }

        return array_values(array_unique($alternatives));
    }

    public function sanitizeCityName(string $city): string
    {
        return str_replace('’', "'", $city);
    }
}
