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
use App\Factory\EventFactory;
use App\Handler\EventHandler;
use App\Import\Cleaner;
use App\Manager\TemporaryFilesManager;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vich\UploaderBundle\Handler\UploadHandler;

final class EventHandlerDownloadTest extends AppKernelTestCase
{
    private const string GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01D\x00;";

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

    /**
     * A downloaded image is the system image: it must not overwrite the hash of the
     * image uploaded by the user.
     */
    public function testDownloadedImageOnlyUpdatesTheSystemImageHash(): void
    {
        $gif = (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
        $client = new MockHttpClient(new MockResponse($gif, ['http_code' => 200]));

        // Persisted (non-null id): uploadFile() only attaches the file, Vich uploads it on flush
        $event = EventFactory::createOne([
            'url' => 'https://example.test/affiche.gif',
            'imageHash' => 'user-image-hash',
        ]);

        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event]);
        } finally {
            $handler->reset();
        }

        $this->assertNotNull($event->getImageSystemFile());
        $this->assertSame(md5($gif), $event->getImageSystemHash());
        $this->assertSame('user-image-hash', $event->getImageHash());
    }

    /**
     * Many sources serve their posters as WebP: they were refused as an unknown format, and
     * the event went without its image.
     */
    public function testAWebpPosterIsKept(): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagewebp($image);
        $webp = (string) ob_get_clean();
        $client = new MockHttpClient(new MockResponse($webp, ['http_code' => 200]));
        $event = EventFactory::createOne(['url' => 'https://example.test/affiche.webp']);

        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event]);
        } finally {
            $handler->reset();
        }

        $this->assertSame(md5($webp), $event->getImageSystemHash());
        $file = $event->getImageSystemFile();
        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertSame('image/webp', $file->getClientMimeType());
    }

    public function testAnSvgPosterIsNotKept(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $client = new MockHttpClient(new MockResponse($svg, ['http_code' => 200]));
        $event = EventFactory::createOne(['url' => 'https://example.test/affiche.svg']);

        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event]);
        } finally {
            $handler->reset();
        }

        $this->assertNull($event->getImageSystemFile());
        $this->assertNull($event->getImageSystemHash());
    }

    public function testAnImageAnnouncedTooLargeIsNotDownloaded(): void
    {
        $client = new MockHttpClient(new MockResponse(self::largeGif(), [
            'http_code' => 200,
            'response_headers' => ['Content-Length' => (string) self::largeGifSize()],
        ]));
        $event = EventFactory::createOne(['url' => 'https://example.test/huge.gif']);

        $this->download($client, $event);

        $this->assertNull($event->getImageSystemFile());
    }

    public function testAnImageGrowingPastTheLimitIsGivenUp(): void
    {
        // No Content-Length: the size is only known once the bytes come
        $client = new MockHttpClient(new MockResponse(self::largeGif(), ['http_code' => 200]));
        $event = EventFactory::createOne(['url' => 'https://example.test/endless.gif']);

        $this->download($client, $event);

        $this->assertNull($event->getImageSystemFile());
    }

    /**
     * A GIF past the limit: its header makes the whole file pass for a GIF.
     *
     * @return iterable<string>
     */
    private static function largeGif(): iterable
    {
        yield self::GIF;
        for ($i = 0; $i <= EventHandler::MAX_IMAGE_BYTES / 1_000_000; ++$i) {
            yield str_repeat("\0", 1_000_000);
        }
    }

    private static function largeGifSize(): int
    {
        return \strlen(self::GIF) + (intdiv(EventHandler::MAX_IMAGE_BYTES, 1_000_000) + 1) * 1_000_000;
    }

    public function testADownloadIsBoundedInTime(): void
    {
        $options = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $requestOptions) use (&$options): MockResponse {
            $options = $requestOptions;

            return new MockResponse('', ['http_code' => 200]);
        });
        $event = new Event();
        $event->setUrl('https://example.test/a.jpg');

        $this->download($client, $event);

        $this->assertGreaterThan(0, $options['max_duration'] ?? 0);
    }

    public function testImagesAreNeverDownloadedFromThePrivateNetwork(): void
    {
        $client = self::getContainer()->get('app.image_download_client');
        $this->assertInstanceOf(HttpClientInterface::class, $client);

        $this->expectException(TransportExceptionInterface::class);
        $client->request('GET', 'http://169.254.169.254/latest/meta-data/')->getStatusCode();
    }

    public function testAnImageTakenDownIsNotDownloadedAgain(): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(self::GIF, ['http_code' => 200]);
        });
        $event = new Event();
        $event->setUrl('https://example.test/affiche.gif');
        $event->setImageRemovedAt(new DateTimeImmutable());

        $this->download($client, $event);

        $this->assertSame(0, $requests);
    }

    private function download(MockHttpClient $client, Event $event): void
    {
        $handler = $this->makeHandler($client);

        try {
            $handler->handleDownloads([$event]);
        } finally {
            $handler->reset();
        }
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
