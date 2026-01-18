<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
 * @property string|null $thumb
 */
final class Thumb extends BaseManipulator
{
    public function __construct(
        /**
         * Maximum image size in pixels.
         */
        private readonly ?int $maxImageSize = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function run(Image $image): Image
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
