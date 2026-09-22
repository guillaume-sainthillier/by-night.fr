<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class StringExtension
{
    /**
     * Upper-cases the first letter only. Twig's |capitalize also lower-cases the rest,
     * which mangles proper nouns and acronyms ("Zénith toulouse métropole", "Dj").
     */
    #[AsTwigFilter(name: 'ucfirst')]
    public function ucfirst(string $text): string
    {
        return mb_ucfirst($text);
    }
}
