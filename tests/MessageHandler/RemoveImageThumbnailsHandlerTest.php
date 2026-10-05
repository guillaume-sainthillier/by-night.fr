<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\MessageHandler;

use App\Message\PurgeCdnCachePrefix;
use App\Message\RemoveImageThumbnails;
use App\MessageHandler\RemoveImageThumbnailsHandler;
use App\Tests\AppKernelTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Each VichUploader mapping renders under the Picasso loader of the same name, in a directory named after its URL
 * segment: "vich" for event_image (its URL alias), the loader name otherwise. A removed image leaves its own
 * directory only.
 */
final class RemoveImageThumbnailsHandlerTest extends AppKernelTestCase
{
    private const string PATH = '2026/06/12/a.jpg';

    /**
     * @param list<string> $segments loader URL segments whose thumbnails of the image are deleted
     */
    #[DataProvider('provideRemovals')]
    public function testDeletesTheThumbnailsOfItsLoader(string $mapping, array $segments): void
    {
        foreach (['vich', 'user_image', 'page_image'] as $segment) {
            $this->thumbs()->write(\sprintf('glide/%s/%s/fit_contain,w_360.avif', $segment, self::PATH), 'thumbnail');
        }
        $this->thumbs()->write('glide/vich/2020/01/01/other.jpg/fit_contain,w_360.avif', 'another image');

        self::getContainer()->get(RemoveImageThumbnailsHandler::class)(new RemoveImageThumbnails(self::PATH, $mapping));

        foreach (['vich', 'user_image', 'page_image'] as $segment) {
            self::assertSame(
                !\in_array($segment, $segments, true),
                $this->thumbs()->fileExists(\sprintf('glide/%s/%s/fit_contain,w_360.avif', $segment, self::PATH)),
                \sprintf('thumbnails under "%s"', $segment),
            );
        }
        self::assertTrue($this->thumbs()->fileExists('glide/vich/2020/01/01/other.jpg/fit_contain,w_360.avif'), 'other images keep theirs');

        $prefixes = array_map(
            static fn (object $message): string => $message instanceof PurgeCdnCachePrefix ? $message->prefix : $message::class,
            array_map(static fn ($envelope): object => $envelope->getMessage(), $this->transport()->getSent()),
        );
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $segment): string => \sprintf('by-night.fr/p/image/glide/%s/%s/', $segment, self::PATH), $segments),
            $prefixes,
            'one Cloudflare prefix purge, under the URL segment',
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideRemovals(): iterable
    {
        yield 'event image: under its URL alias' => ['event_image', ['vich']];
        yield 'user image: under its loader name' => ['user_image', ['user_image']];
        yield 'page image: under its loader name' => ['page_image', ['page_image']];
    }

    private function thumbs(): FilesystemOperator
    {
        return self::getContainer()->get('thumbs.storage');
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.cdn');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
