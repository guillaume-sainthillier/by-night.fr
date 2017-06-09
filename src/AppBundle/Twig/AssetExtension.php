<?php

namespace AppBundle\Twig;

use Symfony\Bridge\Twig\Extension\AssetExtension as BaseAssetExtension;

class AssetExtension extends \Twig_Extension
{
    /**
     * @var BaseAssetExtension
     */
    private $assetExtension;

    /**
     * @var string
     */
    private $env;

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
        if ($this->env !== 'dev') {
            return $this->assetExtension->getAssetUrl($path, $packageName);
        }

        if (isset($this->mappingAssets[$path])) {
            $path = $this->mappingAssets[$path];
        }

        return $this->assetExtension->getAssetUrl($path, $packageName);
    }
}
