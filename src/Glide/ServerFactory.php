<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Glide;

use App\Glide\Manipulators\Thumb;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Encode;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Flip;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Size;
use League\Glide\Manipulators\Watermark;
use League\Glide\Server;

final class ServerFactory extends \League\Glide\ServerFactory
{
    /**
     * {@inheritDoc}
     */
    public static function create(array $config = []): Server
    {
        return (new self($config))->getServer();
    }

    /**
     * {@inheritDoc}
     */
    public function getManipulators(): array
    {
        return [
            new Orientation(),
            new Crop(),
            new Size($this->getMaxImageSize()),
            new Brightness(),
            new Contrast(),
            new Gamma(),
            new Sharpen(),
            new Filter(),
            new Flip(),
            new Blur(),
            new Pixelate(),
            new Watermark($this->getWatermarks(), $this->getWatermarksPathPrefix()),
            new Background(),
            new Thumb($this->getMaxImageSize()),
            new Border(),
            new Encode(),
        ];
    }
}
