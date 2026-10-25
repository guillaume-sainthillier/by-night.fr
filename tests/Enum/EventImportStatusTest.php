<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Enum\EventImportStatus;
use PHPUnit\Framework\TestCase;

final class EventImportStatusTest extends TestCase
{
    public function testTheGraphIsTheOneTheImportFollows(): void
    {
        $edges = [];
        foreach (EventImportStatus::cases() as $to) {
            foreach ($to->allowedFrom() as $from) {
                $edges[] = $from->value . ' → ' . $to->value;
            }
        }

        self::assertSame([
            'uploaded → checking',
            'checking → checked',
            'uploaded → check_failed',
            'checking → check_failed',
            'uploaded → cancelled',
            'checking → cancelled',
            'checked → cancelled',
            'checked → importing',
            'failed → importing',
            'importing → completed',
            'importing → failed',
        ], $edges);
    }

    public function testNothingLeadsBackToAFileJustUploaded(): void
    {
        self::assertSame([], EventImportStatus::Uploaded->allowedFrom());
    }
}
