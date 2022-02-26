<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use League\Glide\Signatures\SignatureFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    public function __construct(private RouterInterface $router, private string $secret)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('thumb', [$this, 'thumb'], ['is_safe' => ['html']]),
            new TwigFunction('thumbAsset', [$this, 'thumbAsset'], ['is_safe' => ['html']]),
        ];
    }

    public function thumb(?string $path, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $parameters['fm'] = 'pjpg';
        if (str_ends_with($path, 'png')) {
            $parameters['fm'] = 'png';
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_url', $parameters, $referenceType);
    }

    public function thumbAsset(string $path, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $parameters['fm'] = 'pjpg';
        if (str_ends_with($path, 'png')) {
            $parameters['fm'] = 'png';
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_asset_url', $parameters, $referenceType);
    }
}
