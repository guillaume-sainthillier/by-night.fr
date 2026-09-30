<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\PurgeCdnCachePrefix;
use App\Message\RemoveImageThumbnails;
use Silarhi\PicassoBundle\Service\ImagePipeline;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Deletes the thumbnails of a removed image from the thumbs storage, then from Cloudflare, which keeps them a year
 * (immutable): one prefix purge covers every size and format of the image, whatever their signed query string.
 * The CDN is purged after the storage, so it cannot fetch a thumbnail back in between.
 */
#[AsMessageHandler]
final readonly class RemoveImageThumbnailsHandler
{
    private const string TRANSFORMER = 'glide';

    private const string LOADER = 'vich';

    public function __construct(
        private ImagePipeline $imagePipeline,
        private UrlGeneratorInterface $urlGenerator,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RemoveImageThumbnails $message): void
    {
        $this->imagePipeline->purge($message->path, self::LOADER, self::TRANSFORMER);

        $this->messageBus->dispatch(new PurgeCdnCachePrefix($this->thumbnailsUrlPrefix($message->path)));
    }

    /**
     * "by-night.fr/p/image/glide/vich/2026/06/12/a.jpg/": Cloudflare prefixes carry no scheme.
     */
    private function thumbnailsUrlPrefix(string $path): string
    {
        // "//by-night.fr/p/image/..."
        $url = $this->urlGenerator->generate('picasso_image', [
            'transformer' => self::TRANSFORMER,
            'loader' => self::LOADER,
            'path' => trim($path, '/') . '/',
        ], UrlGeneratorInterface::NETWORK_PATH);

        return ltrim($url, '/');
    }
}
