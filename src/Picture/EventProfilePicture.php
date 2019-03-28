<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 11/10/2016
 * Time: 18:48.
 */

namespace App\Picture;

use App\Entity\Agenda;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use function strlen;
use function strpos;
use function substr;
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

    public function getOriginalPictureUrl(Agenda $agenda)
    {
        if ($agenda->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($agenda, 'file')
            );
        }

        if ($agenda->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($agenda, 'systemFile')
            );
        }

        if ($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        return $this->packages->getUrl('/img/empty_event.png');
    }

    public function getOriginalPicture(Agenda $agenda)
    {
        if ($agenda->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($agenda, 'file')
            );
        }

        if ($agenda->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($agenda, 'systemFile')
            );
        }

        if ($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        return $this->packages->getUrl('/img/empty_event.png');
    }

    public function getPictureUrl(Agenda $agenda, $thumb = 'thumbs_evenement')
    {
        if ($agenda->getPath()) {
            $webPath = $this->cacheManager->getBrowserPath($this->helper->asset($agenda, 'file'), $thumb);
            $webPath = substr($webPath, strpos($webPath, '/media'), strlen($webPath));

            return $this->packages->getUrl(
                $webPath
            );
        }

        if ($agenda->getSystemPath()) {
            $webPath = $this->cacheManager->getBrowserPath($this->helper->asset($agenda, 'systemFile'), $thumb);
            $webPath = substr($webPath, strpos($webPath, '/media'), strlen($webPath));

            return $this->packages->getUrl(
                $webPath
            );
        }

        if ($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        $webPath = $this->cacheManager->getBrowserPath('img/empty_event.png', $thumb);
        $webPath = substr($webPath, strpos($webPath, '/media'), strlen($webPath));

        return $this->packages->getUrl(
            $webPath
        );
    }

    public function getPicture(Agenda $agenda, $thumb = 'thumbs_evenement')
    {
        if ($agenda->getPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($agenda, 'file'), $thumb);
        }

        if ($agenda->getSystemPath()) {
            return $this->cacheManager->getBrowserPath($this->helper->asset($agenda, 'systemFile'), $thumb);
        }

        if ($agenda->getUrl()) {
            return $agenda->getUrl();
        }

        return $this->cacheManager->getBrowserPath('img/empty_event.png', $thumb);
    }
}
