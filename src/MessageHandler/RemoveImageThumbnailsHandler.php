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
use Silarhi\PicassoBundle\Service\UrlAliases;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    public function __construct(
        private ImagePipeline $imagePipeline,
        #[Autowire(service: 'picasso.url_aliases')]
        private UrlAliases $urlAliases,
        private UrlGeneratorInterface $urlGenerator,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RemoveImageThumbnails $message): void
    {
        // Each VichUploader mapping is served by the Picasso loader of the same name
        $this->imagePipeline->purge($message->path, $message->mapping, self::TRANSFORMER);

        $this->messageBus->dispatch(new PurgeCdnCachePrefix($this->thumbnailsUrlPrefix($message->mapping, $message->path)));
    }

    /**
     * "by-night.fr/p/image/glide/vich/2026/06/12/a.jpg/": Cloudflare prefixes carry no scheme. URLs name a loader by
     * its URL alias when it has one ("vich" for event_image).
     */
    private function thumbnailsUrlPrefix(string $loader, string $path): string
    {
        // "//by-night.fr/p/image/..."
        $url = $this->urlGenerator->generate('picasso_image', [
            'transformer' => $this->urlAliases->transformerSegment(self::TRANSFORMER),
            'loader' => $this->urlAliases->loaderSegment($loader),
            'path' => trim($path, '/') . '/',
        ], UrlGeneratorInterface::NETWORK_PATH);

        return ltrim($url, '/');
    }
}
