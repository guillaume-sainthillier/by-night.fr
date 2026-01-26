<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Entity\Event;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Handler\DoctrineEventHandler;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTime;
use Override;

/**
 * Integration tests for DoctrineEventHandler.
 *
 * These tests require a working database connection and use DAMA Doctrine Test Bundle
 * for automatic transaction rollback between tests.
 */
final class DoctrineEventHandlerTest extends AppKernelTestCase
{
    private DoctrineEventHandler $handler;

    private EventRepository $eventRepository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = self::getContainer()->get(DoctrineEventHandler::class);
        $this->eventRepository = self::getContainer()->get(EventRepository::class);
    }

    public function testInsertNewEvent(): void
    {
        // Arrange: Create a new event DTO with required fields
        $dto = new EventDto();
        $dto->name = 'Test Concert 2025';
        $dto->description = 'This is a test concert happening in 2025';
        $dto->startDate = new DateTime('+1 week');
        $dto->endDate = new DateTime('+1 week +3 hours');
        $dto->externalId = 'test-event-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';

        // Create place
        $placeDto = new PlaceDto();
        $placeDto->name = 'Test Venue';
        $placeDto->street = '123 Test Street';

        $dto->place = $placeDto;

        // Act: Insert the event
        $this->handler->handleOne($dto);

        // Assert: Check that the event was inserted in the database
        $events = $this->eventRepository->findBy(['externalId' => 'test-event-001']);

        $this->assertCount(1, $events, 'Event should be inserted in database');
        $event = $events[0];
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('Test Concert 2025', $event->getName());
        $this->assertSame('This is a test concert happening in 2025', $event->getDescription());
        $this->assertSame('test-event-001', $event->getExternalId());
        $this->assertSame('test-parser', $event->getExternalOrigin());
        $this->assertNotNull($event->getId(), 'Event should have an ID after insertion');
    }

    public function testMergeWithExistingEvent(): void
    {
        // Arrange: Create an existing event in the database
        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Paris', 'country' => $country]);
        $place = PlaceFactory::createOne([
            'name' => 'Original Venue',
            'city' => $city,
            'country' => $country,
        ]);
        $user = UserFactory::createOne();

        $existingEvent = EventFactory::createOne([
            'name' => 'Original Event Name',
            'description' => 'Original description',
            'externalId' => 'merge-test-001',
            'externalOrigin' => 'test-parser',
            'place' => $place,
            'user' => $user,
            'startDate' => new DateTime('+1 week'),
            'endDate' => new DateTime('+1 week +2 hours'),
        ]);

        $originalId = $existingEvent->getId();

        // Act: Create a DTO with the same external ID but different content
        $dto = new EventDto();
        $dto->name = 'Updated Event Name';
        $dto->description = 'Updated description with more details';
        $dto->externalId = 'merge-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->startDate = new DateTime('+1 week');
        $dto->endDate = new DateTime('+1 week +3 hours');
        $dto->parserVersion = '1.0';

        $placeDto = new PlaceDto();
        $placeDto->name = 'Updated Venue';
        $placeDto->street = '456 Updated Street';

        $dto->place = $placeDto;

        $this->handler->handleOne($dto);

        // Assert: Check that the event was updated, not duplicated
        $events = $this->eventRepository->findBy(['externalId' => 'merge-test-001']);

        $this->assertCount(1, $events, 'Should still have only one event with this external ID');
        $event = $events[0];
        $this->assertSame($originalId, $event->getId(), 'Event ID should remain the same');
        $this->assertSame('Updated Event Name', $event->getName(), 'Name should be updated');
        $this->assertSame('Updated description with more details', $event->getDescription(), 'Description should be updated');
    }

    public function testNoDuplicatesByExternalId(): void
    {
        // Arrange: Insert the same event twice
        $dto1 = new EventDto();
        $dto1->name = 'Duplicate Test Event';
        $dto1->description = 'This event should not be duplicated';
        $dto1->startDate = new DateTime('+2 weeks');
        $dto1->endDate = new DateTime('+2 weeks +1 hour');
        $dto1->externalId = 'duplicate-test-001';
        $dto1->externalOrigin = 'test-parser';
        $dto1->parserVersion = '1.0';

        $placeDto1 = new PlaceDto();
        $placeDto1->name = 'Test Venue';
        $placeDto1->street = '789 Test Ave';

        $dto1->place = $placeDto1;

        // First insertion
        $this->handler->handleOne($dto1);

        // Create a second DTO with the same external ID
        $dto2 = new EventDto();
        $dto2->name = 'Duplicate Test Event Modified';
        $dto2->description = 'This is a modified version';
        $dto2->startDate = new DateTime('+2 weeks');
        $dto2->endDate = new DateTime('+2 weeks +2 hours');
        $dto2->externalId = 'duplicate-test-001';
        $dto2->externalOrigin = 'test-parser';
        $dto2->parserVersion = '1.0';

        $placeDto2 = new PlaceDto();
        $placeDto2->name = 'Test Venue';
        $placeDto2->street = '789 Test Ave';

        $dto2->place = $placeDto2;

        // Act: Second insertion with same external ID
        $this->handler->handleOne($dto2);

        // Assert: Verify only one event exists
        $events = $this->eventRepository->findBy([
            'externalId' => 'duplicate-test-001',
            'externalOrigin' => 'test-parser',
        ]);

        $this->assertCount(1, $events, 'Should have exactly one event, not duplicates');
        $this->assertSame('Duplicate Test Event Modified', $events[0]->getName(), 'Event should be updated with new data');
    }

    public function testMultipleEventsWithDifferentExternalIds(): void
    {
        // Arrange & Act: Insert multiple events with different external IDs
        $dtos = [];
        for ($i = 1; $i <= 3; ++$i) {
            $dto = new EventDto();
            $dto->name = 'Event Number ' . $i;
            $dto->description = 'Description for event ' . $i;
            $dto->startDate = new DateTime(\sprintf('+%d weeks', $i));
            $dto->endDate = new DateTime(\sprintf('+%d weeks +1 hour', $i));
            $dto->externalId = 'multi-test-00' . $i;
            $dto->externalOrigin = 'test-parser';
            $dto->parserVersion = '1.0';

            $placeDto = new PlaceDto();
            $placeDto->name = 'Venue ' . $i;
            $placeDto->street = $i . '00 Street';
            $dto->place = $placeDto;

            $dtos[] = $dto;
        }

        $this->handler->handleMany($dtos);

        // Assert: Verify all three events were inserted
        $event1 = $this->eventRepository->findOneBy(['externalId' => 'multi-test-001']);
        $event2 = $this->eventRepository->findOneBy(['externalId' => 'multi-test-002']);
        $event3 = $this->eventRepository->findOneBy(['externalId' => 'multi-test-003']);

        $this->assertNotNull($event1, 'First event should be inserted');
        $this->assertNotNull($event2, 'Second event should be inserted');
        $this->assertNotNull($event3, 'Third event should be inserted');

        $this->assertSame('Event Number 1', $event1->getName());
        $this->assertSame('Event Number 2', $event2->getName());
        $this->assertSame('Event Number 3', $event3->getName());
    }

    public function testInvalidEventIsFiltered(): void
    {
        // Arrange: Create a DTO with invalid data (too short name and description)
        $dto = new EventDto();
        $dto->name = 'AB'; // Too short (minimum 3 characters for non-affiliates)
        $dto->description = 'Short'; // Too short (minimum 10 characters)
        $dto->startDate = new DateTime('+1 week');
        $dto->endDate = new DateTime('+1 week +1 hour');
        $dto->externalId = 'invalid-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';

        $placeDto = new PlaceDto();
        $placeDto->name = 'Test Venue';
        $placeDto->street = '123 Street';

        $dto->place = $placeDto;

        // Act: Try to insert the invalid event
        $this->handler->handleOne($dto);

        // Assert: Verify the event was NOT inserted
        $events = $this->eventRepository->findBy(['externalId' => 'invalid-test-001']);

        $this->assertCount(0, $events, 'Invalid event should be filtered out and not inserted');
    }

    public function testPlaceAssociationDuringInsertion(): void
    {
        // Create event DTO with place
        $dto = new EventDto();
        $dto->name = 'Concert at MSG';
        $dto->description = 'A concert at Madison Square Garden';
        $dto->startDate = new DateTime('+1 month');
        $dto->endDate = new DateTime('+1 month +3 hours');
        $dto->externalId = 'place-assoc-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';

        // Create a place DTO
        $placeDto = new PlaceDto();
        $placeDto->name = 'Madison Square Garden';
        $placeDto->street = '4 Pennsylvania Plaza';

        $dto->place = $placeDto;

        // Act: Insert the event
        $this->handler->handleOne($dto);

        // Assert: Verify the event is inserted with a place
        $event = $this->eventRepository->findOneBy(['externalId' => 'place-assoc-test-001']);

        $this->assertNotNull($event, 'Event should be inserted');
        $this->assertNotNull($event->getPlace(), 'Event should have an associated place');
        $this->assertSame('Madison Square Garden', $event->getPlace()->getName());
        $this->assertSame('4 Pennsylvania Plaza', $event->getPlace()->getStreet());
    }

    public function testBatchInsertionPerformance(): void
    {
        // Arrange: Create a batch of 10 events
        $dtos = [];
        for ($i = 1; $i <= 10; ++$i) {
            $dto = new EventDto();
            $dto->name = 'Batch Event ' . $i;
            $dto->description = \sprintf('This is batch event number %d with sufficient description length', $i);
            $dto->startDate = new DateTime(\sprintf('+%d days', $i));
            $dto->endDate = new DateTime(\sprintf('+%d days +2 hours', $i));
            $dto->externalId = \sprintf('batch-test-%03d', $i);
            $dto->externalOrigin = 'test-parser';
            $dto->parserVersion = '1.0';

            $placeDto = new PlaceDto();
            $placeDto->name = 'Batch Venue ' . $i;
            $placeDto->street = 'Batch Street ' . $i;
            $dto->place = $placeDto;

            $dtos[] = $dto;
        }

        // Act: Insert all events in one batch
        $startTime = microtime(true);
        $this->handler->handleMany($dtos);
        $duration = microtime(true) - $startTime;

        // Assert: Verify all events were inserted
        $insertedEvents = $this->eventRepository->createQueryBuilder('e')
            ->where('e.externalId LIKE :prefix')
            ->setParameter('prefix', 'batch-test-%')
            ->getQuery()
            ->getResult();

        $this->assertCount(10, $insertedEvents, 'All 10 events should be inserted');
        $this->assertLessThan(5.0, $duration, 'Batch insertion should complete in reasonable time');
    }

    public function testEventWithAllContactInformation(): void
    {
        // Arrange: Create event with full contact information
        $dto = new EventDto();
        $dto->name = 'Event with Contacts';
        $dto->description = 'This event has all contact information filled';
        $dto->startDate = new DateTime('+3 weeks');
        $dto->endDate = new DateTime('+3 weeks +4 hours');
        $dto->externalId = 'contacts-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';
        $dto->phoneContacts = ['+1-555-0123', '+1-555-0124'];
        $dto->emailContacts = ['info@example.com', 'tickets@example.com'];
        $dto->websiteContacts = ['https://example.com', 'https://tickets.example.com'];

        $placeDto = new PlaceDto();
        $placeDto->name = 'Contact Test Venue';
        $placeDto->street = '999 Contact St';

        $dto->place = $placeDto;

        // Act: Insert the event
        $this->handler->handleOne($dto);

        // Assert: Verify contact information was saved
        $event = $this->eventRepository->findOneBy(['externalId' => 'contacts-test-001']);

        $this->assertNotNull($event);
        $this->assertSame(['+1-555-0123', '+1-555-0124'], $event->getPhoneContacts());
        $this->assertSame(['info@example.com', 'tickets@example.com'], $event->getMailContacts());
        $this->assertSame(['https://example.com', 'https://tickets.example.com'], $event->getWebsiteContacts());
    }

    public function testEventTimestampsAreSet(): void
    {
        // Arrange: Create a new event
        $dto = new EventDto();
        $dto->name = 'Timestamp Test Event';
        $dto->description = 'Testing that timestamps are properly set on creation';
        $dto->startDate = new DateTime('+4 weeks');
        $dto->endDate = new DateTime('+4 weeks +1 hour');
        $dto->externalId = 'timestamp-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';

        $placeDto = new PlaceDto();
        $placeDto->name = 'Timestamp Venue';
        $placeDto->street = '111 Time St';

        $dto->place = $placeDto;

        // Act: Insert the event
        $beforeInsert = new DateTime();
        $this->handler->handleOne($dto);
        $afterInsert = new DateTime();

        // Assert: Verify timestamps are within expected range
        $event = $this->eventRepository->findOneBy(['externalId' => 'timestamp-test-001']);

        $this->assertNotNull($event);
        $this->assertNotNull($event->getCreatedAt(), 'Created timestamp should be set');
        $this->assertNotNull($event->getUpdatedAt(), 'Updated timestamp should be set');

        $createdAt = $event->getCreatedAt();
        $this->assertGreaterThanOrEqual($beforeInsert->getTimestamp(), $createdAt->getTimestamp());
        $this->assertLessThanOrEqual($afterInsert->getTimestamp(), $createdAt->getTimestamp());
    }

    public function testInsertEventWithTimesheets(): void
    {
        // Arrange: Create event DTO with timesheets
        $dto = new EventDto();
        $dto->name = 'Multi-day Festival';
        $dto->description = 'A festival with multiple time slots over several days';
        $dto->externalId = 'timesheet-test-001';
        $dto->externalOrigin = 'test-parser';
        $dto->parserVersion = '1.0';

        // Create timesheets
        $timesheet1 = new EventTimesheetDto();
        $timesheet1->startAt = new DateTime('2024-06-15 10:00:00');
        $timesheet1->endAt = new DateTime('2024-06-15 18:00:00');
        $timesheet1->hours = 'De 10h à 18h';

        $timesheet2 = new EventTimesheetDto();
        $timesheet2->startAt = new DateTime('2024-06-16 12:00:00');
        $timesheet2->endAt = new DateTime('2024-06-16 22:00:00');
        $timesheet2->hours = 'De 12h à 22h';

        $timesheet3 = new EventTimesheetDto();
        $timesheet3->startAt = new DateTime('2024-06-17 14:00:00');
        $timesheet3->endAt = new DateTime('2024-06-17 20:00:00');
        $timesheet3->hours = 'De 14h à 20h';

        $dto->timesheets = [$timesheet1, $timesheet2, $timesheet3];

        // startDate/endDate should be computed from timesheets
        $dto->startDate = new DateTime('2024-06-15');
        $dto->endDate = new DateTime('2024-06-17');

        $placeDto = new PlaceDto();
        $placeDto->name = 'Festival Grounds';
        $placeDto->street = '1 Festival Way';
        $dto->place = $placeDto;

        // Act: Insert the event
        $this->handler->handleOne($dto);

        // Assert: Verify timesheets were saved
        $event = $this->eventRepository->findOneBy(['externalId' => 'timesheet-test-001']);

        $this->assertNotNull($event, 'Event should be inserted');
        $this->assertCount(3, $event->getTimesheets(), 'Event should have 3 timesheets');

        // Verify start/end dates are computed from timesheets (min/max)
        $this->assertEquals('2024-06-15', $event->getStartDate()->format('Y-m-d'), 'Start date should be min of timesheets');
        $this->assertEquals('2024-06-17', $event->getEndDate()->format('Y-m-d'), 'End date should be max of timesheets');

        // Verify timesheet data
        $timesheets = $event->getTimesheets()->toArray();
        $this->assertEquals('De 10h à 18h', $timesheets[0]->getHours());
        $this->assertEquals('2024-06-15 10:00:00', $timesheets[0]->getStartAt()->format('Y-m-d H:i:s'));
    }

    public function testUpdateEventTimesheetsReplacesExisting(): void
    {
        // Arrange: Create an event with timesheets
        $dto1 = new EventDto();
        $dto1->name = 'Evolving Event';
        $dto1->description = 'This event will have its timesheets updated';
        $dto1->externalId = 'timesheet-update-001';
        $dto1->externalOrigin = 'test-parser';
        $dto1->parserVersion = '1.0';
        $dto1->startDate = new DateTime('2024-07-01');
        $dto1->endDate = new DateTime('2024-07-02');

        $timesheet1 = new EventTimesheetDto();
        $timesheet1->startAt = new DateTime('2024-07-01 10:00:00');
        $timesheet1->endAt = new DateTime('2024-07-01 18:00:00');
        $timesheet1->hours = 'Original hours';

        $dto1->timesheets = [$timesheet1];

        $placeDto = new PlaceDto();
        $placeDto->name = 'Update Test Venue';
        $placeDto->street = '100 Update St';
        $dto1->place = $placeDto;

        $this->handler->handleOne($dto1);

        // Act: Update with new timesheets
        $dto2 = new EventDto();
        $dto2->name = 'Evolving Event';
        $dto2->description = 'This event will have its timesheets updated';
        $dto2->externalId = 'timesheet-update-001';
        $dto2->externalOrigin = 'test-parser';
        $dto2->parserVersion = '1.0';
        $dto2->startDate = new DateTime('2024-07-10');
        $dto2->endDate = new DateTime('2024-07-12');

        $newTimesheet1 = new EventTimesheetDto();
        $newTimesheet1->startAt = new DateTime('2024-07-10 09:00:00');
        $newTimesheet1->endAt = new DateTime('2024-07-10 17:00:00');
        $newTimesheet1->hours = 'New hours day 1';

        $newTimesheet2 = new EventTimesheetDto();
        $newTimesheet2->startAt = new DateTime('2024-07-12 11:00:00');
        $newTimesheet2->endAt = new DateTime('2024-07-12 19:00:00');
        $newTimesheet2->hours = 'New hours day 2';

        $dto2->timesheets = [$newTimesheet1, $newTimesheet2];
        $dto2->place = $placeDto;

        $this->handler->handleOne($dto2);

        // Assert: Verify timesheets were replaced
        $event = $this->eventRepository->findOneBy(['externalId' => 'timesheet-update-001']);

        $this->assertNotNull($event);
        $this->assertCount(2, $event->getTimesheets(), 'Old timesheets should be replaced with new ones');

        $timesheets = $event->getTimesheets()->toArray();
        $hours = array_map(static fn ($t) => $t->getHours(), $timesheets);
        $this->assertContains('New hours day 1', $hours);
        $this->assertContains('New hours day 2', $hours);
        $this->assertNotContains('Original hours', $hours);

        // Verify dates were recomputed
        $this->assertEquals('2024-07-10', $event->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2024-07-12', $event->getEndDate()->format('Y-m-d'));
    }
}
