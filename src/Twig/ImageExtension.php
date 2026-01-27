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
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

final class ImageExtension
{
    public function __construct(
        private readonly ImageHelper $imageHelper,
    ) {
    }

    #[AsTwigFunction(name: 'image', isSafe: ['html'], needsEnvironment: true)]
    public function image(Environment $twig, array $params): string
    {
        return $this->imageHelper->image($params, $twig);
    }
}
