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
use League\Glide\Server;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveImageThumbnailsHandler
{
    public function __construct(
        #[Autowire(service: 'app.s3_thumb_server')]
        private Server $s3ThumbServer,
    ) {
    }

    public function __invoke(RemoveImageThumbnails $message): void
    {
        $this->s3ThumbServer->deleteCache($message->path);
    }
}
