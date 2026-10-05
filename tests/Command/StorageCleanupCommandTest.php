<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\StorageCleanupCommand;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Message\PurgeCdnCacheUrl;
use App\Message\RemoveImageThumbnails;
use App\Storage\ImageCachePurger;
use App\Storage\OrphanedUploadsCleaner;
use App\Storage\UploadMappings;
use App\Tests\AppKernelTestCase;
use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

final class StorageCleanupCommandTest extends AppKernelTestCase
{
    /** @var list<string> keys the command deleted */
    private array $deleted = [];

    /** @var list<object> */
    private array $dispatched = [];

    public function testAFileUploadedMomentsAgoIsNotDeleted(): void
    {
        $this->cleanUp(['uploads/documents' => [
            // Its row may still be in an open transaction: no name references it yet
            $this->file('uploads/documents/2026/09/22/just-uploaded.jpg', '-5 minutes'),
            $this->file('uploads/documents/2019/03/15/orphan.jpg'),
        ]]);

        self::assertSame(['uploads/documents/2019/03/15/orphan.jpg'], $this->deleted);
    }

    /**
     * The names come from every column a Vich mapping stores them in: an event's poster and a country's cover are
     * kept, the file nobody names goes, with its thumbnails and its CDN copy.
     */
    public function testAFileNoEntityNamesIsDeletedWithItsCopies(): void
    {
        EventFactory::createOne(['image' => $this->named('poster.jpg')]);
        CountryFactory::createOne(['heroImage' => $this->named('cover.jpg')]);

        $this->cleanUp([
            'uploads/documents' => [$this->file('uploads/documents/2019/03/15/poster.jpg'), $this->file('uploads/documents/2019/03/15/orphan.jpg')],
            'uploads/countries' => [$this->file('uploads/countries/cover.jpg'), $this->file('uploads/countries/old-cover.jpg')],
        ]);

        self::assertSame(['uploads/documents/2019/03/15/orphan.jpg', 'uploads/countries/old-cover.jpg'], $this->deleted);
        self::assertEquals([
            new RemoveImageThumbnails('2019/03/15/orphan.jpg', 'event_image'),
            new PurgeCdnCacheUrl('/uploads/documents/2019/03/15/orphan.jpg'),
            new RemoveImageThumbnails('old-cover.jpg', 'country_image'),
            new PurgeCdnCacheUrl('/uploads/countries/old-cover.jpg'),
        ], $this->dispatched);
    }

    public function testADryRunDeletesNothing(): void
    {
        $tester = $this->cleanUp(['uploads/documents' => [$this->file('uploads/documents/2019/03/15/orphan.jpg')]], ['--dry-run' => true]);

        self::assertSame([], $this->deleted);
        self::assertStringContainsString('Would delete 1 orphaned files', $tester->getDisplay());
    }

    /**
     * @param array<string, list<array<string, mixed>>> $files   the listing of each folder of the bucket
     * @param array<string, mixed>                      $options
     */
    private function cleanUp(array $files, array $options = []): CommandTester
    {
        $mappings = self::getContainer()->get(UploadMappings::class);

        // One listing per mapping folder, in their order, then the deletions
        $handler = new MockHandler();
        foreach ($mappings->getFolders() as $folder) {
            $handler->append(new Result(['IsTruncated' => false, 'Contents' => $files[$folder] ?? []]));
        }

        for ($i = 0; $i < 10; ++$i) {
            $handler->append(function (CommandInterface $command): Result {
                $this->deleted[] = $command['Key'];

                return new Result([]);
            });
        }

        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };

        $client = new S3Client(['region' => 'eu-west-3', 'version' => 'latest', 'credentials' => false, 'handler' => $handler]);
        $cleaner = new OrphanedUploadsCleaner(self::getContainer()->get(Connection::class), $client, 'bucket', $mappings, new ImageCachePurger($bus));
        $tester = new CommandTester(new StorageCleanupCommand($cleaner));
        $tester->execute($options);
        $this->dispatched = $bus->messages;

        return $tester;
    }

    /**
     * @return array{Key: string, Size: int, LastModified: DateTimeResult}
     */
    private function file(string $key, string $lastModified = '2019-03-15'): array
    {
        return ['Key' => $key, 'Size' => 10, 'LastModified' => new DateTimeResult($lastModified)];
    }

    private function named(string $name): EmbeddedFile
    {
        $file = new EmbeddedFile();
        $file->setName($name);

        return $file;
    }
}
