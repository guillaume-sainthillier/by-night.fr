<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\EventIndexableChecker;
use App\Entity\Event;
use PHPUnit\Framework\TestCase;

class EventIndexableCheckerTest extends TestCase
{
    private EventIndexableChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new EventIndexableChecker();
    }

    public function testIsIndexableReturnsTrueForNonDraftNonDuplicateEvent(): void
    {
        $event = new Event();
        $event->setDraft(false);

        self::assertTrue($this->checker->isIndexable($event));
    }

    public function testIsIndexableReturnsFalseForDraftEvent(): void
    {
        $event = new Event();
        $event->setDraft(true);

        self::assertFalse($this->checker->isIndexable($event));
    }

    public function testIsIndexableReturnsFalseForDuplicateEvent(): void
    {
        $canonical = new Event();
        $canonical->setDraft(false);

        $duplicate = new Event();
        $duplicate->setDraft(false);
        $duplicate->setDuplicateOf($canonical);

        self::assertFalse($this->checker->isIndexable($duplicate));
    }

    public function testIsIndexableReturnsFalseForDraftDuplicateEvent(): void
    {
        $canonical = new Event();

        $duplicate = new Event();
        $duplicate->setDraft(true);
        $duplicate->setDuplicateOf($canonical);

        self::assertFalse($this->checker->isIndexable($duplicate));
    }

    public function testIsIndexableReturnsTrueForCanonicalEventWithDuplicates(): void
    {
        $canonical = new Event();
        $canonical->setDraft(false);

        // This event has other events pointing to it, but that doesn't make it a duplicate
        self::assertTrue($this->checker->isIndexable($canonical));
    }
}
