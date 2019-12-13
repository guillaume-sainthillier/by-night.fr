<?php

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
