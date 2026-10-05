<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * The has_image column the database computes from the image names (Event::$hasImage), which Doctrine never writes:
 * an insert or an update that wrote it would fail.
 */
final class EventHasImageTest extends AppKernelTestCase
{
    public function testTheColumnTellsTheEventsWithAnUploadedOrAFetchedPicture(): void
    {
        $uploaded = EventFactory::createOne(['image' => $this->picture('poster.jpg')]);
        $fetched = EventFactory::createOne(['imageSystem' => $this->picture('fetched.jpg')]);
        $both = EventFactory::createOne(['image' => $this->picture('poster.jpg'), 'imageSystem' => $this->picture('fetched.jpg')]);
        $blank = EventFactory::createOne(['image' => $this->picture(''), 'imageSystem' => $this->picture('')]);
        $none = EventFactory::createOne();

        foreach ([$uploaded, $fetched, $both, $blank, $none] as $event) {
            refresh($event);
        }

        self::assertTrue($uploaded->hasImageAsLoaded());
        self::assertTrue($fetched->hasImageAsLoaded());
        self::assertTrue($both->hasImageAsLoaded());
        self::assertFalse($blank->hasImageAsLoaded());
        self::assertFalse($none->hasImageAsLoaded());
        self::assertEqualsCanonicalizing([$uploaded->getId(), $fetched->getId(), $both->getId()], $this->idsWithImage());
    }

    public function testTheColumnFollowsTheChangesOfTheImageNames(): void
    {
        $event = EventFactory::createOne();
        self::assertSame([], $this->idsWithImage());

        $event->setImageSystem($this->picture('fetched.jpg'));
        save($event);
        self::assertSame([$event->getId()], $this->idsWithImage());
        refresh($event);
        self::assertTrue($event->hasImageAsLoaded());

        $event->setImageSystem($this->picture(null));
        save($event);
        self::assertSame([], $this->idsWithImage());
        refresh($event);
        self::assertFalse($event->hasImageAsLoaded());
    }

    /**
     * @return list<int|null>
     */
    private function idsWithImage(): array
    {
        return array_map(static fn (Event $event): ?int => $event->getId(), EventFactory::findBy(['hasImage' => true]));
    }

    private function picture(?string $name): EmbeddedFile
    {
        $picture = new EmbeddedFile();
        $picture->setName($name);

        return $picture;
    }
}
