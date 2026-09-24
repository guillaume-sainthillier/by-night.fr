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
use FOS\ElasticaBundle\Serializer\Callback;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The event document as config/packages/fos_elastica.yaml has it built: one entry per session,
 * and every field serialized even when it is null.
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

    /**
     * An indexed event is sent as an update merged into the stored document, so a field that
     * became null has to be part of the document as null: left out, the merge would keep the
     * value the field had, and a deleted category or a removed place would stay searchable.
     */
    public function testAFieldThatBecameNullIsStillPartOfTheDocument(): void
    {
        $event = new Event();
        $event->setName('Concert sans lieu');
        $event->setStartDate(new DateTimeImmutable('2026-11-05'));
        $event->setEndDate(new DateTimeImmutable('2026-11-05'));

        $callback = self::getContainer()->get('fos_elastica.index.event.serializer.callback');
        self::assertInstanceOf(Callback::class, $callback);
        $document = json_decode($callback->serialize($event), true);

        self::assertIsArray($document);
        foreach (['description', 'category', 'place', 'placeName', 'placeCity', 'type'] as $field) {
            self::assertArrayHasKey($field, $document, \sprintf('"%s" must be sent so the update clears it', $field));
            self::assertNull($document[$field]);
        }
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
