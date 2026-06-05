<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Factory\EventFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ContentRemovalEventDeletionListenerTest extends KernelTestCase
{
    use Factories;
    use MailerAssertionsTrait;
    use ResetDatabase;

    public function testRequesterIsNotifiedWhenLinkedEventIsDeleted(): void
    {
        $entityManager = $this->createRequestLinkedTo(['requester@example.com' => 1]);

        $event = $entityManager->getRepository(Event::class)->findAll()[0];
        $entityManager->remove($event);
        $entityManager->flush();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'requester@example.com');
        self::assertEmailHtmlBodyContains($email, 'a été supprimé');

        // A pending request whose event is purged outside the admin workflow is auto-closed.
        $entityManager->clear();
        $request = $entityManager->getRepository(ContentRemovalRequest::class)->findOneBy(['email' => 'requester@example.com']);
        self::assertNotNull($request);
        self::assertSame(ContentRemovalRequestStatus::Processed, $request->getStatus());
        self::assertNotNull($request->getProcessedAt());
        self::assertNull($request->getProcessedBy());
    }

    public function testNoEmailWhenDeletedEventHasNoRemovalRequest(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $event = EventFactory::createOne()->_real();
        $entityManager->remove($event);
        $entityManager->flush();

        self::assertEmailCount(0);
    }

    public function testRequesterIsNotifiedOnceWhenSeveralLinkedEventsAreDeleted(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $firstEvent = EventFactory::createOne()->_real();
        $secondEvent = EventFactory::createOne()->_real();

        $request = new ContentRemovalRequest();
        $request->setEmail('owner@example.com');
        $request->setType(ContentRemovalType::Event);
        $request->setMessage('These two events are mine, please remove them.');
        $request->addEvent($firstEvent);
        $request->addEvent($secondEvent);
        $entityManager->persist($request);
        $entityManager->flush();

        $entityManager->remove($firstEvent);
        $entityManager->remove($secondEvent);
        $entityManager->flush();

        // A single e-mail despite two deleted events linked to the same request.
        self::assertEmailCount(1);
    }

    /**
     * @param array<string, int> $emails requester e-mail => number of events to link
     */
    private function createRequestLinkedTo(array $emails): EntityManagerInterface
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        foreach ($emails as $email => $eventCount) {
            $request = new ContentRemovalRequest();
            $request->setEmail($email);
            $request->setType(ContentRemovalType::Event);
            $request->setMessage('Please remove this content as I own the rights.');

            for ($i = 0; $i < $eventCount; ++$i) {
                $request->addEvent(EventFactory::createOne()->_real());
            }

            $entityManager->persist($request);
        }

        $entityManager->flush();

        return $entityManager;
    }
}
