<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Picture;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Picture\EventProfilePicture;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

use function Zenstruck\Foundry\Persistence\save;

final class EventProfilePictureTest extends AppKernelTestCase
{
    public function testThePageShowsThePictureItBorrowsFromItsFamily(): void
    {
        [$canonical, $lender] = $this->borrowing();

        $data = $this->service()->getPicturePathAndSource($canonical);

        self::assertSame($lender, $data['entity']);
        self::assertStringContainsString('cdiscount.jpg', $this->service()->getOriginalPicture($canonical));
    }

    public function testItsEditFormShowsItsOwn(): void
    {
        [$canonical] = $this->borrowing();

        self::assertSame($canonical, $this->service()->getPicturePathAndSource($canonical, own: true)['entity']);
        self::assertStringContainsString('fnac.jpg', $this->service()->getOriginalPicture($canonical, own: true));
    }

    public function testItsOwnPicturesTakenDownTakeTheBorrowedOneDownToo(): void
    {
        [$canonical] = $this->borrowing();
        $canonical->setImageRemovedAt(new DateTimeImmutable());
        save($canonical);

        self::assertSame($canonical, $this->service()->getPicturePathAndSource($canonical)['entity']);
    }

    /**
     * @return array{Event, Event}
     */
    private function borrowing(): array
    {
        $lender = EventFactory::createOne(['imageSystem' => $this->picture('cdiscount.jpg')]);
        $canonical = EventFactory::createOne(['imageSystem' => $this->picture('fnac.jpg'), 'pictureFrom' => $lender]);

        return [$canonical, $lender];
    }

    private function service(): EventProfilePicture
    {
        return self::getContainer()->get(EventProfilePicture::class);
    }

    private function picture(string $name): EmbeddedFile
    {
        $picture = new EmbeddedFile();
        $picture->setName($name);

        return $picture;
    }
}
