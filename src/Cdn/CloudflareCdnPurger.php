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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purges files from the Cloudflare cache within the per-account purge quotas.
 *
 * The request rate is not throttled here: the scoped HTTP clients (config/packages/http_client.yaml) are decorated
 * by Symfony's ThrottlingHttpClient, which waits for a token (config/packages/rate_limiter.yaml) before every
 * request, "cloudflare_purge" for tags and prefixes, "cloudflare_purge_files" for URLs: Cloudflare counts them
 * apart. This class only makes sure a request never carries more URLs than allowed, and turns a refusal for quota
 * into a CdnPurgeQuotaExceededException, retried when Cloudflare says.
 */
final readonly class CloudflareCdnPurger
{
    /** Cloudflare's "max operations per request": a purge call never carries more URLs than this. */
    public const int MAX_FILES_PER_REQUEST = 100;

    public function __construct(
        #[Target('cloudflare.client')]
        private HttpClientInterface $cloudflareClient,
        #[Target('cloudflare_files.client')]
        private HttpClientInterface $cloudflareFilesClient,
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
            $this->request($this->cloudflareFilesClient, ['files' => $chunk]);
        }
    }

    /**
     * Purge every cached response carrying one of these Cache-Tag values (e.g. EventPageCache::TAG).
     *
     * Same chunking as purge(): Cloudflare takes up to MAX_FILES_PER_REQUEST tags per request, but their quota is
     * far smaller (5 requests a minute on the Free plan).
     *
     * @param string[] $tags
     */
    public function purgeTags(array $tags): void
    {
        foreach (array_chunk(array_values(array_unique($tags)), self::MAX_FILES_PER_REQUEST) as $chunk) {
            $this->request($this->cloudflareClient, ['tags' => $chunk]);
        }
    }

    /**
     * Purge every cached URL under these prefixes, whatever their query string (e.g. all the thumbnails of an
     * image, see RemoveImageThumbnailsHandler). A prefix is a host and a path, without scheme:
     * "by-night.fr/p/image/glide/vich/2026/06/12/a.jpg/".
     *
     * Same chunking and quota as purgeTags(): Cloudflare takes up to MAX_FILES_PER_REQUEST prefixes per request.
     *
     * @param string[] $prefixes
     */
    public function purgePrefixes(array $prefixes): void
    {
        foreach (array_chunk(array_values(array_unique($prefixes)), self::MAX_FILES_PER_REQUEST) as $chunk) {
            $this->request($this->cloudflareClient, ['prefixes' => $chunk]);
        }
    }

    /**
     * @param array{files?: list<string>, tags?: list<string>, prefixes?: list<string>} $body
     */
    private function request(HttpClientInterface $client, array $body): void
    {
        $response = $client->request('POST', \sprintf('zones/%s/purge_cache', $this->zoneId), [
            'json' => $body,
        ]);

        if (429 === $response->getStatusCode()) {
            throw new CdnPurgeQuotaExceededException($response->getHeaders(false)['retry-after'][0] ?? null);
        }

        $data = $response->toArray();
        if (!($data['success'] ?? false)) {
            throw new RuntimeException(\sprintf('Cloudflare purge failed: %s', json_encode($data['errors'] ?? [])));
        }
    }
}
