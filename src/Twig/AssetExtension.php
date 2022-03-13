<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Helper\AssetHelper;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    public function __construct(
        private AssetHelper $assetHelper,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('thumb', [$this->assetHelper, 'getThumbUrl'], ['is_safe' => ['html']]),
            new TwigFunction('thumbAsset', [$this->assetHelper, 'getThumbAssetUrl'], ['is_safe' => ['html']]),
        ];
    }
}
