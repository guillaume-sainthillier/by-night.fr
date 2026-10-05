<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Handler\EventImageDownloader;
use App\Message\DownloadEventImages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadEventImagesHandler
{
    public function __construct(
        private EventImageDownloader $eventImageDownloader,
    ) {
    }

    public function __invoke(DownloadEventImages $message): void
    {
        $this->eventImageDownloader->downloadEvents($message->eventIds);
    }
}
