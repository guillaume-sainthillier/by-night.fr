<?php

namespace App\Picture;

use App\Entity\Event;
use App\Parser\Common\DataTourismeParser;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\OpenAgendaParser;
use App\Parser\Common\SowProgParser;
use App\Parser\Toulouse\BikiniParser;
use App\Parser\Toulouse\ToulouseParser;
use App\Twig\AssetExtension;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class EventProfilePicture
{
    /** @var UploaderHelper
     */
    private $helper;

    /** @var Packages
     */
    private $packages;

    /** @var AssetExtension */
    private $assetExtension;

    public function __construct(UploaderHelper $helper, Packages $packages, AssetExtension $assetExtension)
    {
        $this->assetExtension = $assetExtension;
        $this->helper = $helper;
        $this->packages = $packages;
    }

    public function getOriginalPicture(Event $event)
    {
        if ($event->getPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'file'),
                'aws'
            );
        }

        if ($event->getSystemPath()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'systemFile'),
                'aws'
            );
        }

        if ($event->getFromData() === BikiniParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/bikini.jpg');
        }

        if ($event->getFromData() === ToulouseParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/toulouse-tourisme.jpg');
        }

        if ($event->getFromData() === SowProgParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/sowprog.jpg');
        }

        if ($event->getFromData() === OpenAgendaParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/openagenda.jpg');
        }

        if ($event->getFromData() === DataTourismeParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/data-tourisme.jpg');
        }

        if ($event->getFromData() === DigitickAwinParser::getParserName()) {
            return $this->packages->getUrl('build/images/parsers/digitick.jpg');
        }

        if ($event->getUrl()) {
            return $event->getUrl();
        }

        return $this->packages->getUrl(
            AssetExtension::ASSET_PREFIX . '/images/empty_event.png'
        );
    }

    public function getPicture(Event $event, array $params = [])
    {
        if ($event->getPath()) {
            return $this->assetExtension->thumb($this->helper->asset($event, 'file'), $params);
        }

        if ($event->getSystemPath()) {
            return $this->assetExtension->thumb($this->helper->asset($event, 'systemFile'), $params);
        }

        if ($event->getFromData() === BikiniParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/bikini.jpg', 'local'),
                $params
            );
        }

        if ($event->getFromData() === ToulouseParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/toulouse-tourisme.jpg', 'local'),
                $params
            );
        }

        if ($event->getFromData() === SowProgParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/sowprog.jpg', 'local'),
                $params
            );
        }

        if ($event->getFromData() === OpenAgendaParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/openagenda.jpg', 'local'),
                $params
            );
        }

        if ($event->getFromData() === DataTourismeParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/data-tourisme.jpg', 'local'),
                $params
            );
        }

        if ($event->getFromData() === DigitickAwinParser::getParserName()) {
            return $this->assetExtension->thumbAsset(
                $this->packages->getUrl('build/images/parsers/digitick.jpg', 'local'),
                $params
            );
        }

        return $this->assetExtension->thumbAsset(
            $this->packages->getUrl(AssetExtension::ASSET_PREFIX . '/images/empty_event.png', 'local'),
            $params
        );
    }
}
