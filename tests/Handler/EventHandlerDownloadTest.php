<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Entity\Event;
use App\Handler\EventHandler;
use App\Manager\TemporaryFilesManager;
use App\Tests\AppKernelTestCase;
use App\Utils\Cleaner;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Vich\UploaderBundle\Handler\UploadHandler;

final class EventHandlerDownloadTest extends AppKernelTestCase
{
    /**
     * Each image URL must be fetched with a single GET — no preflight HEAD.
     */
    public function testDownloadsUseGetWithoutPreflightHead(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $method . ' ' . $url;

            // Empty body => uploadFile() short-circuits, so no S3/Vich work is needed.
            return new MockResponse('', ['http_code' => 200]);
        });

        $event1 = new Event();
        $event1->setUrl('https://example.test/a.jpg');
        $event2 = new Event();
        $event2->setUrl('https://example.test/b.jpg');

        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event1, $event2]);
        } finally {
            $handler->reset();
        }

        $methods = array_map(static fn (string $r): string => explode(' ', $r)[0], $requests);

        $this->assertSame(['GET', 'GET'], $methods, 'Two URLs should yield two GETs and zero HEADs');
        $this->assertNotContains('HEAD', $methods);
    }

    /**
     * Even a redirecting source URL is requested with a GET and never a preflight
     * HEAD (the GET resolves the redirect itself via max_redirects).
     */
    public function testRedirectingUrlIssuesNoHeadRequest(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $method . ' ' . $url;

            if (str_contains($url, '/redirect')) {
                return new MockResponse('', [
                    'http_code' => 302,
                    'response_headers' => ['Location' => 'https://cdn.example.test/final.jpg'],
                ]);
            }

            return new MockResponse('', ['http_code' => 200]);
        });

        $event = new Event();
        $event->setUrl('https://example.test/redirect');

        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event]);
        } finally {
            $handler->reset();
        }

        $methods = array_map(static fn (string $r): string => explode(' ', $r)[0], $requests);

        $this->assertNotContains('HEAD', $methods, 'No preflight HEAD must be issued, even for a redirecting URL');
        $this->assertContains('GET https://example.test/redirect', $requests, 'The source URL is fetched directly with a GET');
    }

    private function makeHandler(MockHttpClient $client): EventHandler
    {
        return new EventHandler(
            self::getContainer()->get(Cleaner::class),
            new NullLogger(),
            $client,
            self::getContainer()->get(TemporaryFilesManager::class),
            self::getContainer()->get(UploadHandler::class),
        );
    }
}
