<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\EventImport;
use App\Enum\EventImportStatus;
use PHPUnit\Framework\TestCase;

final class EventImportTest extends TestCase
{
    public function testTheCheckCountsEventsAndListsTheirProblemsUpToTheLimit(): void
    {
        $import = new EventImport()->startCheck();
        $error = ['row' => 2, 'reference' => 'A', 'column' => 'Nom', 'message' => 'Le nom manque'];

        $import->addCheckedEvent([], []);
        for ($i = 0; $i < EventImport::MAX_LISTED_PROBLEMS + 5; ++$i) {
            $import->addCheckedEvent([$error], [$error]);
        }

        self::assertSame(EventImportStatus::Checking, $import->getStatus());
        self::assertSame(EventImport::MAX_LISTED_PROBLEMS + 6, $import->getProcessedCount());
        self::assertSame(1, $import->getValidCount());
        self::assertSame(EventImport::MAX_LISTED_PROBLEMS + 5, $import->getInvalidCount());
        self::assertSame(EventImport::MAX_LISTED_PROBLEMS + 5, $import->getWarningCount());
        self::assertCount(EventImport::MAX_LISTED_PROBLEMS, $import->getErrors());
        self::assertCount(EventImport::MAX_LISTED_PROBLEMS, $import->getWarnings());
    }

    public function testANewCheckForgetsThePreviousOne(): void
    {
        $import = new EventImport()->startCheck()->addCheckedEvent([['row' => 2, 'reference' => null, 'column' => null, 'message' => 'x']], []);

        $import->startCheck();

        self::assertSame(0, $import->getProcessedCount());
        self::assertSame(0, $import->getInvalidCount());
        self::assertSame([], $import->getErrors());
    }

    public function testResumingTheImportCountsItsEventsAgain(): void
    {
        $import = new EventImport()->startImport();
        foreach (['created', 'updated', 'unchanged', 'failed'] as $i => $outcome) {
            $import->addOutcome(['row' => $i + 2, 'reference' => null, 'id' => $i, 'name' => 'Concert', 'outcome' => $outcome]);
        }
        self::assertSame([4, 1, 1, 1, 1], [$import->getProcessedCount(), $import->getCreatedCount(), $import->getUpdatedCount(), $import->getUnchangedCount(), $import->getFailedCount()]);

        $import->startImport();

        self::assertSame([0, 0, 0, 0, 0], [$import->getProcessedCount(), $import->getCreatedCount(), $import->getUpdatedCount(), $import->getUnchangedCount(), $import->getFailedCount()]);
        self::assertSame([], $import->getOutcomes());
    }
}
