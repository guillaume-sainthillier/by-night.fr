<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Storage;

use App\Message\PurgeCdnCacheUrl;
use App\Message\RemoveImageThumbnails;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Once a stored image is deleted, the copies of it that outlive the file are purged, asynchronously: its thumbnails
 * (from the thumbs storage, then from Cloudflare: RemoveImageThumbnailsHandler) and its own URL on the CDN.
 */
final readonly class ImageCachePurger
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param string $mapping   the VichUploader mapping of the image, which is also the Picasso loader serving it
     * @param string $uriPrefix the public path of that mapping: "/uploads/documents"
     * @param string $path      the image's path in that mapping: "2026/06/12/a.jpg", or "a.jpg" for a mapping without
     *                          directories
     */
    public function purge(string $mapping, string $uriPrefix, string $path): void
    {
        $path = ltrim($path, '/');

        $this->messageBus->dispatch(new RemoveImageThumbnails($path, $mapping));
        $this->messageBus->dispatch(new PurgeCdnCacheUrl(rtrim($uriPrefix, '/') . '/' . $path));
    }
}
