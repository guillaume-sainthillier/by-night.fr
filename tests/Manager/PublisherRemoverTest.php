<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Enum\EventExportStatus;
use App\Enum\EventImportStatus;
use App\Factory\EventExportFactory;
use App\Factory\EventFactory;
use App\Factory\EventImportFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Manager\PublisherRemover;
use App\Repository\PublisherLegacySlugRepository;
use App\Tests\AppKernelTestCase;
use App\Transfer\TransferFiles;
use LogicException;

final class PublisherRemoverTest extends AppKernelTestCase
{
    public function testItsEventsGoToTheHeirWhoseReferencesWin(): void
    {
        $owner = UserFactory::createOne();
        $publisher = PublisherFactory::createOne(['owner' => $owner, 'name' => 'Le Ramier']);
        $heir = PublisherFactory::createOne(['owner' => $owner, 'name' => 'Le Bikini']);
        $kept = EventFactory::createOne(['user' => $owner, 'publisher' => $heir, 'externalReference' => 'JAZZ']);
        $clashing = EventFactory::createOne(['user' => $owner, 'publisher' => $publisher, 'externalReference' => 'jazz']);
        $moved = EventFactory::createOne(['user' => $owner, 'publisher' => $publisher, 'externalReference' => 'ROCK']);

        $this->remover()->remove($publisher, $heir);

        self::assertSame(0, PublisherFactory::count(['name' => 'Le Ramier']));
        self::assertSame(3, EventFactory::count(['publisher' => $heir]));
        self::assertSame('JAZZ', EventFactory::find(['id' => $kept->getId()])->getExternalReference());
        self::assertNull(EventFactory::find(['id' => $clashing->getId()])->getExternalReference());
        self::assertSame('ROCK', EventFactory::find(['id' => $moved->getId()])->getExternalReference());
        self::assertSame($heir->getId(), self::getContainer()->get(PublisherLegacySlugRepository::class)->findPublisherBySlug('le-ramier')?->getId());
    }

    /**
     * A redirect never goes through a deleted publisher: the URLs that led to it lead to its heir.
     */
    public function testTheUrlsThatLedToItLeadToTheHeir(): void
    {
        $owner = UserFactory::createOne();
        $first = PublisherFactory::createOne(['owner' => $owner, 'name' => 'Le Ramier']);
        $second = PublisherFactory::createOne(['owner' => $owner, 'name' => 'Le Metronum']);
        $third = PublisherFactory::createOne(['owner' => $owner, 'name' => 'Le Bikini']);
        $this->remover()->remove($first, $second);

        $this->remover()->remove($second, $third);

        $legacySlugs = self::getContainer()->get(PublisherLegacySlugRepository::class);
        self::assertSame($third->getId(), $legacySlugs->findPublisherBySlug('le-ramier')?->getId());
        self::assertSame($third->getId(), $legacySlugs->findPublisherBySlug('le-metronum')?->getId());
    }

    public function testWithoutAnHeirItsEventsGoWithIt(): void
    {
        $publisher = PublisherFactory::createOne(['name' => 'Le Ramier']);
        $event = EventFactory::createOne(['publisher' => $publisher]);

        $this->remover()->remove($publisher, null);

        self::assertSame(0, EventFactory::count(['id' => $event->getId()]));
        self::assertNull(self::getContainer()->get(PublisherLegacySlugRepository::class)->findPublisherBySlug('le-ramier'));
    }

    public function testItsJobsGoWithTheirFiles(): void
    {
        $publisher = PublisherFactory::createOne();
        $files = self::getContainer()->get(TransferFiles::class);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "Nom\n");
        $files->upload($path, 'imports/abc/original.csv');
        $import = EventImportFactory::createOne(['publisher' => $publisher, 'user' => $publisher->getOwner(), 'filePath' => 'imports/abc/original.csv', 'status' => EventImportStatus::Completed]);
        EventExportFactory::createOne(['publisher' => $publisher, 'user' => $publisher->getOwner(), 'status' => EventExportStatus::Completed]);

        $this->remover()->remove($publisher, null);

        self::assertSame(0, EventImportFactory::count(['id' => $import->getId()]));
        self::assertSame(0, EventExportFactory::count());
        self::assertFalse($files->exists('imports/abc/original.csv'));
    }

    public function testItIsKeptWhileOneOfItsJobsRuns(): void
    {
        $publisher = PublisherFactory::createOne();
        EventImportFactory::createOne(['publisher' => $publisher, 'user' => $publisher->getOwner(), 'status' => EventImportStatus::Importing]);

        $this->expectException(LogicException::class);
        $this->remover()->remove($publisher, null);
    }

    public function testItsEventsGoToAnotherPublisherOfTheSameOwnerOnly(): void
    {
        $publisher = PublisherFactory::createOne();

        $this->expectException(LogicException::class);
        $this->remover()->remove($publisher, PublisherFactory::createOne());
    }

    private function remover(): PublisherRemover
    {
        return self::getContainer()->get(PublisherRemover::class);
    }
}
