<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Cdn\CloudflareCdnPurger;
use App\Entity\Event;
use App\Factory\EventFactory;
use App\Manager\EventImageRemover;
use App\Message\PurgeCdnCachePrefix;
use App\Message\PurgeCdnCacheTags;
use App\Message\PurgeCdnCacheUrl;
use App\Message\RemoveImageThumbnails;
use App\MessageHandler\PurgeCdnCachePrefixHandler;
use App\MessageHandler\PurgeCdnCacheUrlHandler;
use App\MessageHandler\RemoveImageThumbnailsHandler;
use App\Tests\AppKernelTestCase;
use League\Flysystem\FilesystemOperator;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

/**
 * An image leaves everywhere it is served from once removed (event deleted, image replaced or taken down): the file
 * from the bucket, its rendered thumbnails from the thumbs storage, and both from Cloudflare, which keeps the
 * original and the thumbnails for a year. The whole chain runs here: the messages ImageSubscriber queues, then their
 * handlers, down to the purge requests sent to Cloudflare.
 */
final class ImageRemovalTest extends AppKernelTestCase
{
    /** @var list<array<string, list<string>>> bodies of the purge requests sent to Cloudflare */
    private array $purgeRequests = [];

    public function testDeletingAnEventRemovesItsImageEverywhere(): void
    {
        $event = $this->eventWithImage('affiche.png');
        $path = $this->storedPath($event->getImage()->getName());
        $this->renderThumbnails($path);
        $eventId = $event->getId();

        delete($event);

        self::assertFalse($this->events()->fileExists($path), 'the file is deleted from the bucket');
        self::assertContains('event-' . $eventId, $this->purgedTags(), 'the event page is purged too');
        $this->assertRemovedEverywhere([$path]);
    }

    public function testReplacingAnImageRemovesTheOldOne(): void
    {
        $event = $this->eventWithImage('ancienne.png');
        $oldPath = $this->storedPath($event->getImage()->getName());
        $this->renderThumbnails($oldPath);

        $event->setImageFile($this->png('nouvelle.png'));
        save($event);

        $newPath = $this->storedPath($event->getImage()->getName());
        self::assertTrue($this->events()->fileExists($newPath));
        self::assertFalse($this->events()->fileExists($oldPath));
        $this->assertRemovedEverywhere([$oldPath]);
    }

    public function testTakingDownAnEventsImagesRemovesBothFiles(): void
    {
        $event = $this->eventWithImage('membre.png');
        $event->setImageSystemFile($this->png('source.png'));
        save($event);
        $memberPath = $this->storedPath($event->getImage()->getName());
        $sourcePath = $this->storedPath($event->getImageSystem()->getName());
        $this->renderThumbnails($memberPath);
        $this->renderThumbnails($sourcePath);
        $this->resetTransports();

        self::getContainer()->get(EventImageRemover::class)->remove($event);
        save($event);

        self::assertFalse($this->events()->fileExists($memberPath));
        self::assertFalse($this->events()->fileExists($sourcePath));
        $this->assertRemovedEverywhere([$memberPath, $sourcePath]);
    }

    /**
     * Runs the queued messages through their handlers and checks each image is gone from the thumbs storage and
     * purged from Cloudflare, while another image's thumbnails stay.
     *
     * @param list<string> $paths the removed images, relative to the events storage
     */
    private function assertRemovedEverywhere(array $paths): void
    {
        $this->thumbs()->write('glide/vich/2020/01/01/autre.png/fit_contain,w_360.avif', 'another image');

        $sent = $this->sentMessages();
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $path): RemoveImageThumbnails => new RemoveImageThumbnails($path, 'event_image'), $paths),
            array_values(array_filter($sent, static fn (object $message): bool => $message instanceof RemoveImageThumbnails)),
        );
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $path): PurgeCdnCacheUrl => new PurgeCdnCacheUrl('/uploads/documents/' . $path), $paths),
            array_values(array_filter($sent, static fn (object $message): bool => $message instanceof PurgeCdnCacheUrl)),
        );

        // Thumbnails: deleted from the storage, then a prefix purge queued for Cloudflare
        $this->resetTransports();
        $thumbnailsHandler = self::getContainer()->get(RemoveImageThumbnailsHandler::class);
        foreach ($sent as $message) {
            if ($message instanceof RemoveImageThumbnails) {
                $thumbnailsHandler($message);
            }
        }
        foreach ($paths as $path) {
            self::assertSame([], $this->thumbnailsOf($path), \sprintf('the thumbnails of "%s" are deleted', $path));
        }
        self::assertTrue($this->thumbs()->fileExists('glide/vich/2020/01/01/autre.png/fit_contain,w_360.avif'), 'other thumbnails stay');

        // Cloudflare: the original on the data host, every thumbnail of the image by prefix
        $purger = new CloudflareCdnPurger($this->cloudflare(), $this->cloudflare(), 'zone-123', 'https://data.example.test');
        $urlHandler = new PurgeCdnCacheUrlHandler($purger, new NullLogger());
        $prefixHandler = new PurgeCdnCachePrefixHandler($purger, new NullLogger());
        foreach ($sent as $message) {
            if ($message instanceof PurgeCdnCacheUrl) {
                $urlHandler($message, new Acknowledger(PurgeCdnCacheUrlHandler::class));
            }
        }
        foreach ($this->sentMessages() as $message) {
            if ($message instanceof PurgeCdnCachePrefix) {
                $prefixHandler($message, new Acknowledger(PurgeCdnCachePrefixHandler::class));
            }
        }
        $urlHandler->flush(true);
        $prefixHandler->flush(true);

        self::assertEqualsCanonicalizing([
            ['files' => array_map(static fn (string $path): string => 'https://data.example.test/uploads/documents/' . $path, $paths)],
            ['prefixes' => array_map(static fn (string $path): string => 'by-night.fr/p/image/glide/vich/' . $path . '/', $paths)],
        ], array_map(static fn (array $body): array => array_map(static function (array $values): array {
            sort($values);

            return $values;
        }, $body), $this->purgeRequests));
    }

    private function eventWithImage(string $name): Event
    {
        $event = EventFactory::createOne();
        $event->setImageFile($this->png($name));
        save($event);
        $this->resetTransports();

        return $event;
    }

    /**
     * Thumbnails the way Picasso's public cache writes them: under glide/<loader URL segment>/<image path>/, "vich"
     * for event images (event_image's URL alias, config/packages/picasso.yaml).
     */
    private function renderThumbnails(string $path): void
    {
        $this->thumbs()->write(\sprintf('glide/vich/%s/fit_contain,fm_avif,h_535,w_730.avif', $path), 'avif');
        $this->thumbs()->write(\sprintf('glide/vich/%s/fit_contain,fm_webp,h_535,w_730.webp', $path), 'webp');
        self::assertCount(2, $this->thumbnailsOf($path));
    }

    /**
     * @return list<string>
     */
    private function thumbnailsOf(string $path): array
    {
        $files = [];
        foreach ($this->thumbs()->listContents('glide/vich/' . $path, true) as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }

        return $files;
    }

    private function storedPath(?string $name): string
    {
        self::assertNotNull($name);

        // CurrentDateTimeDirectoryNamer: under the event's creation date
        foreach ($this->events()->listContents('', true) as $item) {
            if ($item->isFile() && str_ends_with($item->path(), $name)) {
                return $item->path();
            }
        }

        self::fail(\sprintf('"%s" is not in the storage', $name));
    }

    private function png(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(4, 4), $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function cloudflare(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringEndsWith('zones/zone-123/purge_cache', $url);
            $body = json_decode((string) $options['body'], true);
            self::assertIsArray($body);
            $this->purgeRequests[] = $body;

            return new MockResponse('{"success":true,"errors":[],"messages":[],"result":{"id":"x"}}');
        }, 'https://api.cloudflare.test/client/v4/');
    }

    /**
     * @return list<string>
     */
    private function purgedTags(): array
    {
        $tags = [];
        foreach ($this->sentMessages() as $message) {
            if ($message instanceof PurgeCdnCacheTags) {
                array_push($tags, ...$message->tags);
            }
        }

        return $tags;
    }

    /**
     * @return list<object>
     *
     * @phpstan-impure
     */
    private function sentMessages(): array
    {
        $messages = [];
        foreach ($this->transports() as $transport) {
            foreach ($transport->getSent() as $envelope) {
                $messages[] = $envelope->getMessage();
            }
        }

        return $messages;
    }

    private function resetTransports(): void
    {
        foreach ($this->transports() as $transport) {
            $transport->reset();
        }
    }

    /**
     * The thumbnail removals go to "async", the Cloudflare purges to "cdn".
     *
     * @return list<InMemoryTransport>
     */
    private function transports(): array
    {
        $transports = [];
        foreach (['async', 'cdn'] as $name) {
            $transport = self::getContainer()->get('messenger.transport.' . $name);
            self::assertInstanceOf(InMemoryTransport::class, $transport);
            $transports[] = $transport;
        }

        return $transports;
    }

    private function events(): FilesystemOperator
    {
        return self::getContainer()->get('events.storage');
    }

    private function thumbs(): FilesystemOperator
    {
        return self::getContainer()->get('thumbs.storage');
    }
}
