<?php

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
     * @var integer|null
     */
    protected $maxImageSize;

    public function __construct($maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform background image manipulation.
     * @param Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        if (is_null($this->thumb)) {
            return $image;
        }

        $new = clone $image;
        $blur = new Blur();
        $blur->setParams(['blur' => 90]);
        $new = $blur->run($new);
        $size = new Size($this->maxImageSize);
        $size->setParams($this->params + ['fit' => 'stretch']);
        $new = $size->run($new);

        $image = $new->insert($image, 'center-center', 0, 0);

        return $image;
    }
}
