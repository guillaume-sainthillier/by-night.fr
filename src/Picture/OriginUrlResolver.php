<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Maps a Picasso image path back to the public URL of the original file.
 *
 * Consumers that drop the query string of a Picasso URL (its signature) produce a path
 * we cannot serve, and the only thing left to send them to is the origin file, which
 * means working out which storage it sits in. Since Picasso 2 each upload mapping has
 * its own loader, so the URL names the storage, except for the "vich" segment: event
 * images (event_image's URL alias) and every upload in a 1.x URL.
 */
final readonly class OriginUrlResolver
{
    /**
     * A hit is stable: a file does not move between storages. A miss is usually
     * permanent too (a deleted event, a stale build hash), but is re-checked daily so
     * that a file restored from a backup starts resolving again on its own. A probe
     * that errored is retried shortly: an unreachable storage must not be mistaken
     * for a missing file and then cached as one for a day.
     */
    private const int HIT_TTL = 2592000;

    private const int MISS_TTL = 86400;

    private const int ERROR_TTL = 60;

    /**
     * Trailing public-cache params segment, e.g. "/fit_contain,fm_jpg,h_253,w_360.jpg".
     * Anchoring on the known Glide keys is what keeps an ordinary file name such as
     * "my_photo.jpg" from being mistaken for one and stripped.
     */
    private const string PARAMS_SEGMENT = '#/(?:w|h|fm|q|fit|blur|dpr)_[^/,]+(?:,(?:w|h|fm|q|fit|blur|dpr)_[^/,]+)*\.[a-z0-9]+$#i';

    /**
     * Public prefix of each upload storage (they mirror the S3 prefixes in
     * config/packages/flysystem.yaml), ordered by how many files it holds: an event
     * image is by far the most likely, so the common case costs a single probe.
     *
     * @var list<string>
     */
    private const array UPLOAD_PREFIXES = [
        'uploads/documents',
        'uploads/users',
        'uploads/pages',
        'uploads/cities',
        'uploads/countries',
    ];

    /**
     * Upload storage read by each Picasso loader named after its VichUploader mapping
     * (config/packages/picasso.yaml).
     *
     * @var array<string, string>
     */
    private const array LOADER_UPLOAD_PREFIXES = [
        'user_image' => 'uploads/users',
        'page_image' => 'uploads/pages',
        'city_image' => 'uploads/cities',
        'country_image' => 'uploads/countries',
    ];

    private const string LEGACY_UPLOAD_LOADER = 'vich';

    public function __construct(
        private Packages $packages,
        private CacheInterface $memoryCache,
        private LoggerInterface $logger,
        /**
         * A locator rather than one argument per storage: probing stops at the first hit, so
         * the storages further down the list — and the S3 clients behind them — are
         * never built on the common path.
         */
        #[AutowireLocator([
            'uploads/documents' => new Autowire(service: 'events.storage'),
            'uploads/users' => new Autowire(service: 'users.storage'),
            'uploads/pages' => new Autowire(service: 'pages.storage'),
            'uploads/cities' => new Autowire(service: 'cities.storage'),
            'uploads/countries' => new Autowire(service: 'countries.storage'),
        ])]
        private ContainerInterface $uploadStorages,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    /**
     * @return string|null the public URL of the original file, or null when no storage holds it
     */
    public function resolve(string $loader, string $path): ?string
    {
        $path = preg_replace(self::PARAMS_SEGMENT, '', $path) ?? $path;

        // The path comes straight from the URL, and feeds a local is_file() below.
        if ('' === $path || str_contains($path, '..')) {
            return null;
        }

        /** @var string|null $url */
        $url = $this->memoryCache->get(
            // The cached value is a full URL: the namespace was bumped when build assets moved
            // from static.by-night.fr to the app host, so entries pointing at the old host expire.
            'picasso_origin.v2.' . hash('xxh128', $loader . "\0" . $path),
            function (ItemInterface $item) use ($loader, $path): ?string {
                [$url, $degraded] = match (true) {
                    'filesystem' === $loader => [$this->resolvePublicFile($path), false],
                    self::LEGACY_UPLOAD_LOADER === $loader => $this->probeUploadStorages($path, self::UPLOAD_PREFIXES),
                    isset(self::LOADER_UPLOAD_PREFIXES[$loader]) => $this->probeUploadStorages($path, [self::LOADER_UPLOAD_PREFIXES[$loader]]),
                    default => [null, false],
                };

                $item->expiresAfter(match (true) {
                    $degraded => self::ERROR_TTL,
                    null === $url => self::MISS_TTL,
                    default => self::HIT_TTL,
                });

                return $url;
            },
        );

        return $url;
    }

    /**
     * The filesystem loader is rooted on public/, so its path is already the public
     * one — a single stat, no ambiguity to resolve.
     */
    private function resolvePublicFile(string $path): ?string
    {
        if (!is_file($this->projectDir . '/public/' . $path)) {
            return null;
        }

        return $this->packages->getUrl($path);
    }

    /**
     * Probe the given upload storages in turn, stopping at the first hit.
     *
     * Guessing instead of probing is not an option for "vich" URLs — the second most
     * requested of them is a *user* image (1.x URL), so a popularity heuristic would
     * emit a permanent redirect to a dead object.
     *
     * @param list<string> $prefixes
     *
     * @return array{0: string|null, 1: bool} the URL, and whether a probe errored
     */
    private function probeUploadStorages(string $path, array $prefixes): array
    {
        $degraded = false;

        foreach ($prefixes as $prefix) {
            try {
                /** @var FilesystemOperator $storage */
                $storage = $this->uploadStorages->get($prefix);

                if ($storage->fileExists($path)) {
                    return [$this->packages->getUrl($prefix . '/' . $path, 's3'), false];
                }
            } catch (FilesystemException|ContainerExceptionInterface $e) {
                $degraded = true;

                $this->logger->warning('Could not probe "{prefix}" for image "{path}": {message}', [
                    'prefix' => $prefix,
                    'path' => $path,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return [null, $degraded];
    }
}
