<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The event document carries one entry per session (config/packages/fos_elastica.yaml),
 * produced by the same serializer group the index is built with.
 */
final class EventDocumentTest extends AppKernelTestCase
{
    private const array GROUPS = ['groups' => ['elasticsearch:event:details']];

    public function testSessionsAreIndexedOnePerTimesheetInChronologicalOrder(): void
    {
        $event = new Event();
        $event->setName('Atelier poterie');
        $event->setStartDate(new DateTimeImmutable('2026-10-03'));
        $event->setEndDate(new DateTimeImmutable('2026-10-10'));
        $event->addTimesheet($this->sessionOn('2026-10-10'));
        $event->addTimesheet($this->sessionOn('2026-10-03'));

        $document = $this->normalizer()->normalize($event, null, self::GROUPS);

        self::assertIsArray($document);
        self::assertSame('2026-10-03', $document['startDate']);
        self::assertSame(
            [
                ['startAt' => '2026-10-03', 'endAt' => '2026-10-03'],
                ['startAt' => '2026-10-10', 'endAt' => '2026-10-10'],
            ],
            $document['sessions'],
        );
    }

    public function testAnEventWithoutTimesheetsIsIndexedAsOneSessionSpanningItsRange(): void
    {
        $event = new Event();
        $event->setName('Exposition');
        $event->setStartDate(new DateTimeImmutable('2026-09-01'));
        $event->setEndDate(new DateTimeImmutable('2026-12-31'));

        $document = $this->normalizer()->normalize($event, null, self::GROUPS);

        self::assertIsArray($document);
        self::assertSame([['startAt' => '2026-09-01', 'endAt' => '2026-12-31']], $document['sessions']);
    }

    private function normalizer(): NormalizerInterface
    {
        $normalizer = self::getContainer()->get('serializer');
        self::assertInstanceOf(NormalizerInterface::class, $normalizer);

        return $normalizer;
    }

    private function sessionOn(string $date): EventTimesheet
    {
        return new EventTimesheet()
            ->setStartAt(new DateTimeImmutable($date))
            ->setEndAt(new DateTimeImmutable($date));
    }
}
