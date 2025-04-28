<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Helper\AssetHelper;
use App\Parser\Common\DataTourismeParser;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\OpenAgendaParser;
use App\Parser\Common\SowProgParser;
use App\Parser\Toulouse\BikiniParser;
use App\Parser\Toulouse\ToulouseParser;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

final readonly class EventProfilePicture
{
    public function __construct(
        private UploaderHelper $helper,
        private Packages $packages,
        private AssetHelper $assetHelper,
    ) {
    }

    public function getOriginalPicture(Event|EventDto $event): string
    {
        [
            'path' => $path,
            'source' => $source,
        ] = $this->getPicturePathAndSource($event);

        if ('upload' === $source) {
            return $this->packages->getUrl(
                $path,
                'aws'
            );
        }

        return $this->packages->getUrl($path);
    }

    public function getPicture(Event|EventDto $event, array $params = []): string
    {
        [
            'path' => $path,
            'source' => $source,
        ] = $this->getPicturePathAndSource($event);

        if ('upload' === $source) {
            return $this->assetHelper->getThumbS3Url($path, $params);
        }

        return $this->assetHelper->getThumbAssetUrl(
            $path,
            $params
        );
    }

    public function getPicturePathAndSource(Event|EventDto $event): array
    {
        $image = $event instanceof EventDto
            ? $event->image
            : $event->getImage();

        if ($image->getName()) {
            return [
                'path' => $this->helper->asset($event, 'imageFile'),
                'source' => 'upload',
            ];
        }

        $systemImage = $event instanceof EventDto
            ? null
            : $event->getImageSystem();
        if ($systemImage?->getName()) {
            return [
                'path' => $this->helper->asset($event, 'imageSystemFile'),
                'source' => 'upload',
            ];
        }

        $fromData = $event instanceof EventDto
            ? $event->fromData
            : $event->getFromData();

        if ($fromData === BikiniParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/bikini.jpg', 'local'),
                'source' => 'local',
            ];
        }

        if ($fromData === ToulouseParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/toulouse-tourisme.jpg', 'local'),
                'source' => 'local',
            ];
        }

        if ($fromData === SowProgParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/sowprog.jpg', 'local'),
                'source' => 'local',
            ];
        }

        if ($fromData === OpenAgendaParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/openagenda.jpg', 'local'),
                'source' => 'local',
            ];
        }

        if ($fromData === DataTourismeParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/data-tourisme.jpg', 'local'),
                'source' => 'local',
            ];
        }

        if ($fromData === DigitickAwinParser::getParserName()) {
            return [
                'path' => $this->packages->getUrl('build/images/parsers/digitick.jpg', 'local'),
                'source' => 'local',
            ];
        }

        return [
            'path' => $this->packages->getUrl('build/images/empty_event.png', 'local'),
            'source' => 'local',
        ];
    }
}
