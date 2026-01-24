<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Helper\AssetHelper;
use Twig\Attribute\AsTwigFunction;

final class AssetExtension
{
    public function __construct(
        private readonly AssetHelper $assetHelper,
    ) {
    }

    #[AsTwigFunction(name: 'thumb_asset', isSafe: ['html'])]
    public function thumbAsset(string $path, array $parameters = []): string
    {
        return $this->assetHelper->getThumbAssetUrl($path, $parameters);
    }
}
