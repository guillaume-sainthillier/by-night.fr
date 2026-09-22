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
use App\Import\Cleaner;
use App\Manager\TemporaryFilesManager;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
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

        return new EventsDownloadImagesCommand(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(EventRepository::class),
            $handler,
        );
    }
}
