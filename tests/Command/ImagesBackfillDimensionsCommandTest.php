<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Factory\EventFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Storage\StorageInterface;

final class ImagesBackfillDimensionsCommandTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    private StorageInterface $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->storage = self::getContainer()->get(StorageInterface::class);
    }

    public function testBackfillsDimensionsFromTheStoredFiles(): void
    {
        // A user upload and an imported image, both stored without dimensions
        $upload = EventFactory::createOne(['image' => $this->image('poster.png')]);
        $this->store($upload, 'imageFile', 'events.storage', 8, 5);
        $imported = EventFactory::createOne(['imageSystem' => $this->image('remote.png')]);
        $this->store($imported, 'imageSystemFile', 'events.storage', 3, 2);

        // Already measured, or no image at all: not candidates
        $measured = EventFactory::createOne(['image' => $this->image('measured.png', [640, 480])]);
        $bare = EventFactory::createOne();

        // Users go through the same code path, on their own bucket
        $avatar = UserFactory::createOne(['image' => $this->image('avatar.png')]);
        $this->store($avatar, 'imageFile', 'users.storage', 4, 4);

        // The fixtures above are stale in the identity map; the command must load fresh rows
        $this->entityManager->clear();

        $display = $this->doRunCommand([])->getDisplay();

        $this->assertStringContainsString('Image dimensions backfilled', $display);
        $this->assertSame([8, 5], $this->dimensions(EventFactory::find($upload->getId())->getImage()));
        $this->assertSame([3, 2], $this->dimensions(EventFactory::find($imported->getId())->getImageSystem()));
        $this->assertSame([640, 480], $this->dimensions(EventFactory::find($measured->getId())->getImage()));
        $this->assertNull($this->dimensions(EventFactory::find($bare->getId())->getImage()));
        $this->assertSame([4, 4], $this->dimensions(UserFactory::find($avatar->getId())->getImage()));

        // Idempotent: a second run finds nothing to do
        $this->assertStringContainsString('0 row(s) with an image but no dimensions', $this->doRunCommand(['--entity' => ['event']])->getDisplay());
    }

    public function testUnreadableFilesAreReportedAndLeftAlone(): void
    {
        $missing = EventFactory::createOne(['image' => $this->image('gone.png')]);
        $garbage = EventFactory::createOne(['image' => $this->image('garbage.png')]);
        $this->write($garbage, 'imageFile', 'events.storage', 'definitely not an image');
        $this->entityManager->clear();

        $display = $this->doRunCommand(['--entity' => ['event']], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE])->getDisplay();

        $this->assertStringContainsString('Unreadable files:', $display);
        $this->assertStringContainsString(\sprintf('#%d imageFile:', $missing->getId()), $display);
        $this->assertStringContainsString(\sprintf('#%d imageFile:', $garbage->getId()), $display);
        $this->assertNull($this->dimensions(EventFactory::find($missing->getId())->getImage()));
        $this->assertNull($this->dimensions(EventFactory::find($garbage->getId())->getImage()));
    }

    public function testClearMissingKeepsTheImagesOfAStorageItCannotReach(): void
    {
        $event = EventFactory::createOne(['image' => $this->image('poster.png'), 'imageHash' => 'kept-hash']);
        $this->entityManager->clear();
        // The bucket times out: Vich's resolveStream() answers null as for a missing object
        self::getContainer()->set('events.storage', new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function fileExists(string $path): bool
            {
                throw UnableToCheckFileExistence::forLocation($path);
            }

            public function read(string $path): string
            {
                throw UnableToReadFile::fromLocation($path, 'timeout');
            }

            public function readStream(string $path): void
            {
                throw UnableToReadFile::fromLocation($path, 'timeout');
            }
        }));

        $display = $this->doRunCommand(['--entity' => ['event'], '--clear-missing' => true])->getDisplay();

        $event = EventFactory::find($event->getId());
        $this->assertTrue($event->hasImage(), 'An unreachable file is no proof that it is gone');
        $this->assertSame('kept-hash', $event->getImageHash());
        $this->assertStringContainsString('Unreachable (kept)', $display);
    }

    public function testClearMissingErasesTheImageAndResetsItsHash(): void
    {
        // Missing from every bucket: a user upload, an imported image, and an avatar
        $upload = EventFactory::createOne(['image' => $this->image('gone.png'), 'imageHash' => 'upload-hash']);
        $imported = EventFactory::createOne(['imageSystem' => $this->image('gone-too.png'), 'imageSystemHash' => 'system-hash']);
        $avatar = UserFactory::createOne(['image' => $this->image('gone.png'), 'imageHash' => 'avatar-hash']);

        // Readable in the same run: measured, and its hash kept
        $intact = EventFactory::createOne(['image' => $this->image('poster.png'), 'imageHash' => 'kept-hash']);
        $this->store($intact, 'imageFile', 'events.storage', 8, 5);
        $this->entityManager->clear();

        $display = $this->doRunCommand(['--clear-missing' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE])->getDisplay();
        $this->assertStringContainsString('Unreadable files, erased:', $display);

        $event = EventFactory::find($upload->getId());
        $this->assertFalse($event->hasImage());
        $this->assertNull($event->getImage()->getName());
        $this->assertNull($event->getImageHash());

        $event = EventFactory::find($imported->getId());
        $this->assertFalse($event->hasImage());
        $this->assertNull($event->getImageSystemHash());

        $user = UserFactory::find($avatar->getId());
        $this->assertFalse($user->hasImage());
        $this->assertNull($user->getImageHash());

        $event = EventFactory::find($intact->getId());
        $this->assertSame([8, 5], $this->dimensions($event->getImage()));
        $this->assertSame('kept-hash', $event->getImageHash());

        // Erased rows are no longer candidates
        $this->assertStringContainsString('0 row(s) with an image but no dimensions', $this->doRunCommand(['--entity' => ['event']])->getDisplay());
    }

    public function testDryRunReadsButWritesNothing(): void
    {
        $event = EventFactory::createOne(['image' => $this->image('poster.png')]);
        $this->store($event, 'imageFile', 'events.storage', 8, 5);
        $this->entityManager->clear();

        $display = $this->doRunCommand(['--dry-run' => true])->getDisplay();

        $this->assertStringContainsString('nothing was written', $display);
        // The command clears the entity manager, so nothing is left dirty for the proxy to trip on
        $this->assertNull($this->dimensions(EventFactory::find($event->getId())->getImage()));
    }

    public function testRejectsAnUnknownEntity(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:images:backfill-dimensions'));
        $tester->execute(['--entity' => ['comment']]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown entity "comment"', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     */
    private function doRunCommand(array $input, array $options = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:images:backfill-dimensions'));
        $tester->execute($input, $options);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    /**
     * @param int[]|null $dimensions
     */
    private function image(string $name, ?array $dimensions = null): EmbeddedFile
    {
        $image = new EmbeddedFile();
        $image->setName($name);
        $image->setOriginalName($name);
        $image->setMimeType('image/png');
        $image->setSize(42);
        $image->setDimensions($dimensions);

        return $image;
    }

    /**
     * simple_array hydrates a NULL column as [] and the stored numbers as strings.
     *
     * @return int[]|null
     */
    private function dimensions(EmbeddedFile $image): ?array
    {
        $dimensions = $image->getDimensions();

        return null === $dimensions || [] === $dimensions ? null : array_map(intval(...), $dimensions);
    }

    /**
     * Stores a PNG of the given size where Vich expects the entity's file.
     */
    private function store(object $entity, string $fileProperty, string $storageName, int $width, int $height): void
    {
        $this->write($entity, $fileProperty, $storageName, $this->png($width, $height));
    }

    private function write(object $entity, string $fileProperty, string $storageName, string $contents): void
    {
        $path = $this->storage->resolvePath($entity, $fileProperty, null, true);
        $this->assertNotNull($path);

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get($storageName);
        $filesystem->write($path, $contents);
    }

    /**
     * Signature + IHDR chunk: all getimagesize() reads to know a PNG's size.
     */
    private function png(int $width, int $height): string
    {
        $ihdr = 'IHDR' . pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . $ihdr . pack('N', crc32($ihdr));
    }
}
