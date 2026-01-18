<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFilter;

final class UrlExtension extends Extension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('url_decode', 'urldecode'),
        ];
    }
}
