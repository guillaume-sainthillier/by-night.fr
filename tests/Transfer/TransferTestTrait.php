<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Transfer;

use App\Entity\Event;
use App\Entity\EventImport;
use App\Entity\User;
use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Factory\CountryFactory;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Repository\EventImportRepository;
use App\SearchRepository\DuplicateEventFinder;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\EventNameMatcher;
use App\Transfer\Import\EventImportChecker;
use App\Transfer\Import\EventImportRunner;
use App\Transfer\Sheet\SheetReader;
use App\Transfer\Sheet\SheetRow;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use League\Flysystem\FilesystemOperator;
use RuntimeException;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * A member whose spreadsheets are checked and imported for real (France, the pipeline, the private storage), the
 * search for duplicates answering with $lookalikes.
 */
trait TransferTestTrait
{
    /** @var list<Event> */
    private array $lookalikes = [];

    /** The search fails instead of answering: counts the times it was asked */
    private ?int $searchDownCalls = null;

    private User $user;

    private function setUpTransfers(): void
    {
        CountryFactory::createOne(['id' => 'FR', 'name' => 'France', 'postalCodeRegex' => '^\d{5}$']);
        $this->user = UserFactory::createOne(['verified' => true, 'enabled' => true]);

        $finder = $this->createStub(PaginatedFinderInterface::class);
        $finder->method('find')->willReturnCallback(function (): array {
            if (null !== $this->searchDownCalls) {
                ++$this->searchDownCalls;

                throw new RuntimeException('No alive nodes found in your cluster');
            }

            return $this->lookalikes;
        });
        $repositoryManager = $this->createStub(RepositoryManagerInterface::class);
        $repositoryManager->method('getRepository')->willReturn(new EventElasticaRepository($finder));
        self::getContainer()->set(DuplicateEventFinder::class, new DuplicateEventFinder($repositoryManager, new EventNameMatcher()));
    }

    private function check(string $csv, EventImportStatus $status = EventImportStatus::Uploaded): EventImport
    {
        $path = 'imports/' . bin2hex(random_bytes(4)) . '/original.csv';
        $this->storage()->write($path, $csv);
        $import = EventImportFactory::createOne(['user' => $this->user, 'filePath' => $path, 'format' => TransferFormat::Csv, 'status' => $status]);

        self::getContainer()->get(EventImportChecker::class)->check($import);
        refresh($import);

        return $import;
    }

    /**
     * Checks the spreadsheet, then imports what the check kept, as the member's button does.
     */
    private function import(string $csv): EventImport
    {
        $import = $this->check($csv);
        self::assertTrue(self::getContainer()->get(EventImportRepository::class)->transition($import, EventImportStatus::Importing));

        self::getContainer()->get(EventImportRunner::class)->run($import);
        refresh($import);

        return $import;
    }

    /**
     * @return array{headers: list<string>, rows: list<SheetRow>}
     */
    private function readStored(string $path, TransferFormat $format = TransferFormat::Csv): array
    {
        $local = tempnam(sys_get_temp_dir(), 'transfer-test-') . '.' . $format->value;
        file_put_contents($local, $this->storage()->read($path));

        try {
            return new SheetReader()->read($local, $format);
        } finally {
            unlink($local);
        }
    }

    private function storage(): FilesystemOperator
    {
        return self::getContainer()->get('transfers.storage');
    }
}
