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

final readonly class CloudflareCdnPurger
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'CLOUDFLARE_ZONE_ID')]
        private string $zoneId,
        #[Autowire(env: 'CLOUDFLARE_API_TOKEN')]
        private string $apiToken,
        #[Autowire(env: 'S3_PUBLIC_URL')]
        private string $s3Url,
    ) {
    }

    /**
     * Purge a list of relative paths from the Cloudflare cache.
     *
     * @param string[] $paths relative paths (e.g. /uploads/documents/file.jpg)
     */
    public function purge(array $paths): void
    {
        $urls = array_map(fn (string $path): string => rtrim($this->s3Url, '/') . '/' . ltrim($path, '/'), $paths);

        $response = $this->httpClient->request('POST', \sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', $this->zoneId), [
            'auth_bearer' => $this->apiToken,
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
