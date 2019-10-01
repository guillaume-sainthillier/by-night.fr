<?php

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    const ASSET_PREFIX = 'build';

    /**
     * @var Packages
     */
    private $packages;

    public function __construct(Packages $packages)
    {
        $this->packages = $packages;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('app_asset', [$this, 'appAsset']),
        ];
    }

    public function appAsset($path, $packageName = 'asset')
    {
        return $this->packages->getUrl($path, $packageName);
    }
}
