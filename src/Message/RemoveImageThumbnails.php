<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

final readonly class RemoveImageThumbnails
{
    public function __construct(
        public string $path,
        /** VichUploader mapping of the image, which is also the name of the Picasso loader serving it */
        public string $mapping,
    ) {
    }
}
