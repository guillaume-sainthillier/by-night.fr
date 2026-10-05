<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\EventsDownloadImagesCommand;
use App\Factory\EventFactory;
use App\Handler\EventHandler;
use App\Handler\EventImageDownloader;
use App\Import\Cleaner;
use App\Manager\TemporaryFilesManager;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Vich\UploaderBundle\Handler\UploadHandler;

use function Zenstruck\Foundry\Persistence\refresh;

final class EventsDownloadImagesCommandTest extends AppKernelTestCase
{
    public function testEveryEventWaitingForItsImageIsDownloaded(): void
    {
        $events = [];
        foreach (range(1, 3) as $i) {
            $events[] = EventFactory::createOne(['url' => \sprintf('https://cdn.example.org/affiche-%d.png', $i)]);
        }

        // One event per page: a downloaded image takes its event out of the query behind the pages
        new CommandTester($this->command())->execute(['--batch-size' => '1']);

        foreach ($events as $event) {
            refresh($event);
            self::assertNotNull($event->getImageSystem()->getName(), \sprintf('Event #%d kept waiting for its image', $event->getId()));
        }
    }

    /**
     * An image taken down on request is not downloaded again (EventImageRemover), and an event whose source gave none
     * has nothing to wait for.
     */
    public function testATakenDownImageIsNotDownloadedAgain(): void
    {
        $takenDown = EventFactory::createOne(['url' => 'https://cdn.example.org/retiree.png', 'imageRemovedAt' => new DateTimeImmutable('-1 day')]);
        $withoutImage = EventFactory::createOne(['url' => null]);

        self::assertSame(0, self::getContainer()->get(EventRepository::class)->countWaitingForImage());

        new CommandTester($this->command())->execute([]);

        refresh($takenDown);
        refresh($withoutImage);
        self::assertNull($takenDown->getImageSystem()->getName());
        self::assertNull($withoutImage->getImageSystem()->getName());
    }

    private function command(): EventsDownloadImagesCommand
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        $handler = new EventHandler(
            self::getContainer()->get(Cleaner::class),
            new NullLogger(),
            new MockHttpClient(static fn (): MockResponse => new MockResponse((string) $png, ['response_headers' => ['content-type' => 'image/png']])),
            self::getContainer()->get(TemporaryFilesManager::class),
            self::getContainer()->get(UploadHandler::class),
        );

        $repository = self::getContainer()->get(EventRepository::class);

        return new EventsDownloadImagesCommand(
            new EventImageDownloader(self::getContainer()->get(EntityManagerInterface::class), $repository, $handler),
            $repository,
        );
    }
}
