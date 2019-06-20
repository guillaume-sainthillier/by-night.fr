<?php

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    const ASSET_PREFIX = 'prod';

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var array
     */
    private $mappingAssets;

    public function __construct(Packages $packages, array $mappingAssets, bool $debug)
    {
        $this->packages = $packages;
        $this->debug = $debug;
        $this->mappingAssets = $mappingAssets;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('app_asset', [$this, 'appAsset']),
        ];
    }

    public function appAsset($path, $packageName = 'static')
    {
        $path = self::ASSET_PREFIX . '/' . $path;
        if (true === $this->debug) {
            return $this->packages->getUrl($path, $packageName);
        }

        if (isset($this->mappingAssets[$path])) {
            $path = $this->mappingAssets[$path];
        }

        return $this->packages->getUrl($path, $packageName);
    }
}
