<?php

namespace App\Twig;

use League\Glide\Signatures\SignatureFactory;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    const ASSET_PREFIX = 'build';

    /**
     * @var Packages
     */
    private $packages;

    /** @var RouterInterface */
    private $router;

    /** @var string */
    private $secret;

    public function __construct(RouterInterface $router, Packages $packages, string $secret)
    {
        $this->router = $router;
        $this->secret = $secret;
        $this->packages = $packages;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('thumb', [$this, 'thumb']),
            new TwigFunction('thumbAsset', [$this, 'thumbAsset']),
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
