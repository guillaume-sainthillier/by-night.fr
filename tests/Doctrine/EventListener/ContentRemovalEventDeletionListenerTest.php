<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Enum\ContentRemovalRequestStatus;
use App\Factory\ContentRemovalRequestFactory;
use App\Factory\EventFactory;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\refresh;

#[RequiresPhpExtension('mjml')]
#[ResetDatabase]
final class ContentRemovalEventDeletionListenerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testRequesterIsNotifiedWhenLinkedEventIsDeleted(): void
    {
        $event = EventFactory::createOne();
        $request = ContentRemovalRequestFactory::createOne([
            'email' => 'requester@example.com',
            'events' => [$event],
        ]);

        delete($event);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'requester@example.com');
        self::assertEmailHtmlBodyContains($email, 'a été supprimé');

        // A pending request whose event is purged outside the admin workflow is auto-closed.
        refresh($request);
        self::assertSame(ContentRemovalRequestStatus::Processed, $request->getStatus());
        self::assertNotNull($request->getProcessedAt());
        self::assertNull($request->getProcessedBy());
    }

    public function testNoEmailWhenDeletedEventHasNoRemovalRequest(): void
    {
        delete(EventFactory::createOne());

        self::assertEmailCount(0);
    }

    public function testRequesterIsNotifiedOnceWhenSeveralLinkedEventsAreDeleted(): void
    {
        $firstEvent = EventFactory::createOne();
        $secondEvent = EventFactory::createOne();

        ContentRemovalRequestFactory::createOne([
            'email' => 'owner@example.com',
            'message' => 'These two events are mine, please remove them.',
            'events' => [$firstEvent, $secondEvent],
        ]);

        // Both deletions must share a single flush so the listener can deduplicate the
        // request they have in common; deleting them one by one would notify twice.
        flush_after(static function () use ($firstEvent, $secondEvent): void {
            delete($firstEvent);
            delete($secondEvent);
        });

        // A single e-mail despite two deleted events linked to the same request.
        self::assertEmailCount(1);
    }
}
