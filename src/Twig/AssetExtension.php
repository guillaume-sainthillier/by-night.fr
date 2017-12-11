<?php

namespace App\Twig;

use Symfony\Bridge\Twig\Extension\AssetExtension as BaseAssetExtension;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFunction;

class AssetExtension extends Extension
{
    const ASSET_PREFIX = 'prod/';

    /**
     * @var BaseAssetExtension
     */
    private $assetExtension;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var array
     */
    private $mappingAssets;

    public function __construct(BaseAssetExtension $assetExtension, array $mappingAssets, bool $debug)
    {
        $this->assetExtension   = $assetExtension;
        $this->debug            = $debug;
        $this->mappingAssets    = $mappingAssets;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('app_asset', [$this, 'appAsset']),
        ];
    }

    public function appAsset($path, $packageName = null)
    {
        $path = self::ASSET_PREFIX.$path;
        if (true === $this->debug) {
            return $this->assetExtension->getAssetUrl($path, $packageName);
        }

        if (isset($this->mappingAssets[$path])) {
            $path = $this->mappingAssets[$path];
        }

        return $this->assetExtension->getAssetUrl($path, $packageName);
    }
}
