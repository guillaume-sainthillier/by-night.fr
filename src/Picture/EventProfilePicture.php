<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
    private UploaderHelper $helper;

    private Packages $packages;

    private AssetExtension $assetExtension;

    public function __construct(UploaderHelper $helper, Packages $packages, AssetExtension $assetExtension)
    {
        $this->assetExtension = $assetExtension;
        $this->helper = $helper;
        $this->packages = $packages;
    }

    public function getOriginalPicture(Event $event): ?string
    {
        if ($event->getImage()->getName()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'imageFile'),
                'aws'
            );
        }

        if ($event->getImageSystem()->getName()) {
            return $this->packages->getUrl(
                $this->helper->asset($event, 'imageSystemFile'),
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

        return $this->packages->getUrl('build/images/empty_event.png');
    }

    public function getPicture(Event $event, array $params = [])
    {
        if ($event->getImage()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($event, 'imageFile'), $params);
        }

        if ($event->getImageSystem()->getName()) {
            return $this->assetExtension->thumb($this->helper->asset($event, 'imageSystemFile'), $params);
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
            $this->packages->getUrl('build/images/empty_event.png', 'local'),
            $params
        );
    }
}
