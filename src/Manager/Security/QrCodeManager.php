<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes as SVG: no image extension on the server, and sharp at any size
 */
final readonly class QrCodeManager
{
    public function toSvgDataUri(string $content, int $size = 240): string
    {
        $svg = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()))->writeString($content);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
