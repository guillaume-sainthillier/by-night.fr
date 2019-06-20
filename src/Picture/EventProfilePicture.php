<?php


namespace App\Picture;

use App\Entity\Event;
use App\Twig\AssetExtension;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class EventProfilePicture
{
    /**
     * @var CacheManager
     */
    private $cacheManager;

    /** @var UploaderHelper
     */
    private $helper;

    /** @var Packages
     */
    private $packages;

    public function __construct(CacheManager $cacheManager, UploaderHelper $helper, Packages $packages)
    {
        $this->cacheManager = $cacheManager;
        $this->helper = $helper;
        $this->packages = $packages;
    }

    public function getOriginalPictureUrl(Event $event)
    {
        if ($event->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'file')
            );
        }

        if ($event->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'systemFile')
            );
        }

        if ($event->getUrl()) {
            return $event->getUrl();
        }

        return $this->packages->getUrl(AssetExtension::ASSET_PREFIX . '/img/empty_event.png');
    }

    public function getOriginalPicture(Event $event)
    {
        if ($event->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'file')
            );
        }

        if ($event->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'systemFile')
            );
        }

        if ($event->getUrl()) {
            return $event->getUrl();
        }

        return $this->packages->getUrl(AssetExtension::ASSET_PREFIX . '/img/empty_event.png');
    }

    public function getPictureUrl(Event $event, $thumb = 'thumbs_evenement')
    {
        if ($event->getPath()) {
            $webPath = $this->cacheManager->getBrowserPath($this->helper->asset($event, 'file'), $thumb);
            $webPath = \mb_substr($webPath, \mb_strpos($webPath, '/media'), \mb_strlen($webPath));

            return $this->packages->getUrl(
                $webPath
            );
        }

        if ($event->getSystemPath()) {
            $webPath = $this->cacheManager->getBrowserPath($this->helper->asset($event, 'systemFile'), $thumb);
            $webPath = \mb_substr($webPath, \mb_strpos($webPath, '/media'), \mb_strlen($webPath));

            return $this->packages->getUrl(
                $webPath
            );
        }

        if ($event->getUrl()) {
            return $event->getUrl();
        }

        return $this->cacheManager->getBrowserPath(AssetExtension::ASSET_PREFIX . '/img/empty_event.png', $thumb);
    }

    public function getPicture(Event $event, $thumb = 'thumbs_evenement')
    {
        if ($event->getPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($event, 'file'), $thumb);
        }

        if ($event->getSystemPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($event, 'systemFile'), $thumb);
        }

        if ($event->getUrl()) {
            return $event->getUrl();
        }

        return $this->cacheManager->getBrowserPath(AssetExtension::ASSET_PREFIX . '/img/empty_event.png', $thumb);
    }
}
