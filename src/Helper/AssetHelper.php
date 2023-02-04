<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Helper;

use League\Glide\Signatures\SignatureFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class AssetHelper
{
    public function __construct(private RouterInterface $router, private string $secret)
    {
    }

    public function getThumbS3Url(?string $path, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        if (empty($parameters['fm'])) {
            $parameters['fm'] = 'pjpg';
            if (str_ends_with($path, 'png')) {
                $parameters['fm'] = 'png';
            }
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_s3_url', $parameters, $referenceType);
    }

    public function getThumbAssetUrl(string $path, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        if (empty($parameters['fm'])) {
            $parameters['fm'] = 'pjpg';
            if (str_ends_with($path, 'png')) {
                $parameters['fm'] = 'png';
            }
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_asset_url', $parameters, $referenceType);
    }
}
