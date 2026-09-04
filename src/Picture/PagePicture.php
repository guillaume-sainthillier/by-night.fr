<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\Page;
use Symfony\Component\Asset\Packages;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

final readonly class PagePicture
{
    public function __construct(
        private UploaderHelper $helper,
        private Packages $packages,
    ) {
    }

    /**
     * Absolute URL of the uploaded original (used for og:image), null when the page has no image.
     */
    public function getOriginalPicture(Page $page): ?string
    {
        if (!$page->hasImage()) {
            return null;
        }

        return $this->packages->getUrl(
            (string) $this->helper->asset($page, 'imageFile'),
            's3'
        );
    }
}
