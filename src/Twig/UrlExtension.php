<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFilter;

class UrlExtension extends Extension
{
    public function getFilters()
    {
        return [
            new TwigFilter('url_decode', 'urldecode'),
        ];
    }
}
