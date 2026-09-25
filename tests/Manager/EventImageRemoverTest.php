<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Handler\EventImageDownloadScheduler;
use App\Manager\EventImageRemover;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use League\Flysystem\FilesystemOperator;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * A takedown removes the files for real, and the source's image is not downloaded again.
 */
final class EventImageRemoverTest extends AppKernelTestCase
{
    public function testTheImagesAreDeletedFromTheStorage(): void
    {
        $event = EventFactory::createOne(['url' => 'https://example.test/affiche.png']);
        $event->setImageFile($this->png('membre.png'));
        $event->setImageSystemFile($this->png('source.png'));
        save($event);
        $memberImage = $this->path($event->getImage()->getName());
        $sourceImage = $this->path($event->getImageSystem()->getName());
        self::assertTrue($this->storage()->fileExists($memberImage));
        self::assertTrue($this->storage()->fileExists($sourceImage));

        self::getContainer()->get(EventImageRemover::class)->remove($event);
        save($event);
        refresh($event);

        self::assertFalse($this->storage()->fileExists($memberImage));
        self::assertFalse($this->storage()->fileExists($sourceImage));
        self::assertNull($event->getImage()->getName());
        self::assertNull($event->getImageSystem()->getName());
        self::assertNull($event->getImageSystemHash());
        self::assertNotNull($event->getImageRemovedAt());
    }

    public function testAnImageTakenDownIsNotScheduledForDownloadAgain(): void
    {
        $event = new Event();
        $event->setUrl('https://example.test/affiche.png');
        $event->setImageRemovedAt(new DateTimeImmutable());
        $scheduler = self::getContainer()->get(EventImageDownloadScheduler::class);

        $scheduler->schedule($event);

        self::assertSame([], new ReflectionProperty(EventImageDownloadScheduler::class, 'events')->getValue($scheduler));
    }

    private function png(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(4, 4), $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function path(?string $name): string
    {
        self::assertNotNull($name);

        // CurrentDateTimeDirectoryNamer: under the event's creation date
        foreach ($this->storage()->listContents('', true) as $item) {
            if (str_ends_with($item->path(), $name)) {
                return $item->path();
            }
        }

        self::fail(\sprintf('"%s" is not in the storage', $name));
    }

    private function storage(): FilesystemOperator
    {
        return self::getContainer()->get('events.storage');
    }
}
