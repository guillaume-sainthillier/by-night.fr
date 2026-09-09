<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Cdn;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purges files from the Cloudflare cache within the per-account purge quota.
 *
 * The request rate is not throttled here: the "cloudflare.client" scoped HTTP client
 * (config/packages/http_client.yaml) is decorated by Symfony's ThrottlingHttpClient, which
 * waits for a "cloudflare_purge" token (config/packages/rate_limiter.yaml) before every
 * request. This class only makes sure a request never carries more URLs than allowed.
 */
final readonly class CloudflareCdnPurger
{
    /** Cloudflare's "max operations per request": a purge call never carries more URLs than this. */
    public const int MAX_FILES_PER_REQUEST = 100;

    public function __construct(
        private HttpClientInterface $cloudflareClient,
        #[Autowire(env: 'CLOUDFLARE_ZONE_ID')]
        private string $zoneId,
        #[Autowire(env: 'S3_PUBLIC_URL')]
        private string $s3Url,
    ) {
    }

    /**
     * Purge a list of relative paths from the Cloudflare cache.
     *
     * Paths are sent in chunks of MAX_FILES_PER_REQUEST, each one a separate (throttled)
     * request. Chunks already sent when a later one fails stay purged: retrying the whole
     * list only purges them again, which is harmless.
     *
     * @param string[] $paths relative paths (e.g. /uploads/documents/file.jpg)
     */
    public function purge(array $paths): void
    {
        $urls = array_map(fn (string $path): string => rtrim($this->s3Url, '/') . '/' . ltrim($path, '/'), $paths);

        foreach (array_chunk($urls, self::MAX_FILES_PER_REQUEST) as $chunk) {
            $this->purgeUrls($chunk);
        }
    }

    /**
     * @param string[] $urls
     */
    private function purgeUrls(array $urls): void
    {
        $response = $this->cloudflareClient->request('POST', \sprintf('zones/%s/purge_cache', $this->zoneId), [
            'json' => [
                'files' => $urls,
            ],
        ]);

        $data = $response->toArray();
        if (!($data['success'] ?? false)) {
            throw new RuntimeException(\sprintf('Cloudflare purge failed: %s', json_encode($data['errors'] ?? [])));
        }
    }
}
