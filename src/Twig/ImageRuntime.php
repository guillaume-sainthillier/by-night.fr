<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Image\Helper\ImageHelper;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(private ImageHelper $imageHelper)
    {
    }

    public function image(Environment $twig, array $params): string
    {
        return $this->imageHelper->image($params, $twig);
    }
}
