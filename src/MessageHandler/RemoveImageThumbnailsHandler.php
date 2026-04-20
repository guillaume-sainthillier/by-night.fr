<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\RemoveImageThumbnails;
use Silarhi\PicassoBundle\Service\ImagePipeline;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveImageThumbnailsHandler
{
    public function __construct(
        private ImagePipeline $imagePipeline,
    ) {
    }

    public function __invoke(RemoveImageThumbnails $message): void
    {
        $this->imagePipeline->purge($message->path, 'vich', 'glide');
    }
}
