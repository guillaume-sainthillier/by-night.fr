<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Glide\Manipulators;

use Intervention\Image\Image;
use League\Glide\Manipulators\BaseManipulator;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Size;

/**
 * @property string $thumb
 */
class Thumb extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     */
    private ?int $maxImageSize;

    public function __construct(?int $maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform background image manipulation.
     *
     * @param Image $image the source image
     *
     * @return Image the manipulated image
     */
    public function run(Image $image)
    {
        if (null === $this->thumb) {
            return $image;
        }

        $new = clone $image;
        $blur = new Blur();
        $blur->setParams(['blur' => 90]);
        $new = $blur->run($new);
        $size = new Size($this->maxImageSize);
        $size->setParams($this->params + ['fit' => 'stretch']);
        $new = $size->run($new);

        return $new->insert($image, 'center-center');
    }
}
