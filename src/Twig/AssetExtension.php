<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
    private RouterInterface $router;

    private string $secret;

    public function __construct(RouterInterface $router, string $secret)
    {
        $this->router = $router;
        $this->secret = $secret;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('thumb', [$this, 'thumb'], ['is_safe' => ['html']]),
            new TwigFunction('thumbAsset', [$this, 'thumbAsset'], ['is_safe' => ['html']]),
        ];
    }

    public function thumb($path, array $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        $parameters['fm'] = 'pjpg';
        if ('png' === substr($path, -3)) {
            $parameters['fm'] = 'png';
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_url', $parameters, $referenceType);
    }

    public function thumbAsset($path, array $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        $parameters['fm'] = 'pjpg';
        if ('png' === substr($path, -3)) {
            $parameters['fm'] = 'png';
        }

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature($path, $parameters);
        $parameters['path'] = ltrim($path, '/');

        return $this->router->generate('thumb_asset_url', $parameters, $referenceType);
    }
}
