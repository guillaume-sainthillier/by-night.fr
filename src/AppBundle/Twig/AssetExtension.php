<?php

namespace AppBundle\Twig;

use Symfony\Bridge\Twig\Extension\AssetExtension as BaseAssetExtension;

class AssetExtension extends \Twig_Extension
{
    const ASSET_PREFIX = 'prod/';
    /**
     * @var BaseAssetExtension
     */
    private $assetExtension;

    /**
     * @var string
     */
    private $env;

    /**
     * @var array
     */
    private $mappingAssets;

    public function __construct(BaseAssetExtension $assetExtension, array $mappingAssets, $env)
    {
        $this->assetExtension = $assetExtension;
        $this->env            = $env;
        $this->mappingAssets  = $mappingAssets;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('app_asset', [$this, 'appAsset']),
        ];
    }

    public function appAsset($path, $packageName = null)
    {
        $path = self::ASSET_PREFIX.$path;
        if ($this->env === 'dev') {
            return $this->assetExtension->getAssetUrl($path, $packageName);
        }

        if (isset($this->mappingAssets[$path])) {
            $path = $this->mappingAssets[$path];
        }

        return $this->assetExtension->getAssetUrl($path, $packageName);
    }
}
