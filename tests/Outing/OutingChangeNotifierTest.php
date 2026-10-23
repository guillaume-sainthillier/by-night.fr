<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Outing;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Message\CheckOutingChanges;
use App\Outing\OutingChangeNotifier;
use App\Outing\OutingState;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

use function Zenstruck\Foundry\Persistence\save;

final class OutingChangeNotifierTest extends AppKernelTestCase
{
    #[RequiresPhpExtension('mjml')]
    public function testAMemberGoingToACancelledEventHearsOfIt(): void
    {
        [$event, $member, $outing] = $this->outing();
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailHeaderSame($email, 'Subject', "Annulé\u{a0}: Concert du samedi");
        self::assertEmailAddressContains($email, 'To', (string) $member->getEmail());
        self::assertEmailTextBodyContains($email, "L'événement est annulé.");
        self::assertEmailHtmlBodyContains($email, 'L&#039;événement est annulé.');
        self::assertEmailHeaderSame($email, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        self::assertMatchesRegularExpression(
            \sprintf('#^<https://by-night\.test/alertes-sorties/%d/desabonnement\?_hash=[^>]+>$#', $member->getId()),
            (string) $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString(),
        );

        $outing = UserEventFactory::find(['id' => $outing->getId()]);
        self::assertSame('cancelled', $outing->getKnownState()?->status);
        self::assertNotNull($outing->getAlertedAt());
    }

    #[RequiresPhpExtension('mjml')]
    public function testAMovedEventSaysItsNewDatesAndVenue(): void
    {
        [$event] = $this->outing();
        $event
            ->setStartDate(new DateTimeImmutable('+20 days'))
            ->setEndDate(new DateTimeImmutable('+20 days'))
            ->setPlace(PlaceFactory::createOne(['name' => 'Le Zénith']));
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'Subject', "Changement de dates\u{a0}: Concert du samedi");
        self::assertEmailTextBodyContains($email, "Nouvelles dates\u{a0}: le ");
        self::assertEmailTextBodyContains($email, "Nouveau lieu\u{a0}: Le Zénith");
    }

    public function testNothingChangedSendsNothing(): void
    {
        [$event] = $this->outing();
        $event->setName('Concert du samedi soir');
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    public function testTheAuthorOfTheEventHearsNothing(): void
    {
        $author = UserFactory::createOne();
        [$event] = $this->outing(member: $author, event: ['user' => $author]);
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    /**
     * The member managing the event's publisher edits it (EventVoter): the changes are theirs, created by another or not.
     */
    public function testTheMemberManagingThePublisherHearsNothing(): void
    {
        $manager = UserFactory::createOne();
        [$event] = $this->outing(member: $manager, event: ['publisher' => PublisherFactory::createOne(['owner' => $manager])]);
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    public function testAMemberWhoTurnedTheAlertsOffHearsNothingButKnowsTheEventAsItIs(): void
    {
        [$event, , $outing] = $this->outing(member: UserFactory::createOne(['outingAlerts' => false]));
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
        // Turned on again, the alerts would not tell this cancellation
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
    }

    public function testARowOlderThanTheAlertsLearnsTheEventWithoutAnAlert(): void
    {
        [$event, , $outing] = $this->outing(known: false);
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
    }

    public function testAnEventOverHasNothingLeftToTell(): void
    {
        [$event] = $this->outing(day: '-3 days');
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    public function testAnEventItsAuthorHidHasNothingToTellYet(): void
    {
        [$event, , $outing] = $this->outing();
        $event->setDraft(true)->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
        self::assertNull(UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status, 'told once published again');
    }

    public function testAMemberTakingBackTheirJyVaisHearsNothing(): void
    {
        [$event] = $this->outing(going: false);
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    public function testADuplicateTellsNothingItsPageLeadsToItsCanonical(): void
    {
        [$event] = $this->outing(event: ['duplicateOf' => EventFactory::createOne()]);
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
    }

    public function testAChangeAfterTheDaysAlertIsToldTheNextMorning(): void
    {
        [$event, , $outing] = $this->outing();
        $outing->setAlertedAt(new DateTimeImmutable());
        save($outing);
        $event->setStatus(EventStatus::Cancelled);
        save($event);
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();

        $this->notifier()->notify([(int) $event->getId()]);

        self::assertEmailCount(0);
        // Still to tell
        self::assertNull(UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
        $held = array_values(array_filter($transport->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof CheckOutingChanges));
        self::assertCount(1, $held);
        $message = $held[0]->getMessage();
        self::assertInstanceOf(CheckOutingChanges::class, $message);
        self::assertSame([$event->getId()], $message->eventIds);
        $delay = (int) $held[0]->last(DelayStamp::class)?->getDelay();
        self::assertSame(new DateTimeImmutable('tomorrow 09:00')->format('Y-m-d H'), new DateTimeImmutable()->modify(\sprintf('+%d seconds', intdiv($delay, 1000)))->format('Y-m-d H'));
    }

    /**
     * A member going to a concert on Saturday, who knows it as it is.
     *
     * @param array<string, mixed> $event
     *
     * @return array{Event, User, UserEvent}
     */
    private function outing(?User $member = null, array $event = [], string $day = '+5 days', bool $going = true, bool $known = true): array
    {
        $member ??= UserFactory::createOne();
        $concert = EventFactory::new()->withDates(new DateTimeImmutable($day))->create(['name' => 'Concert du samedi', ...$event]);
        $outing = UserEventFactory::createOne(['user' => $member, 'event' => $concert, 'going' => $going, 'wish' => false]);
        if ($known) {
            $outing->setKnownState(OutingState::fromEvent($concert));
            save($outing);
        }

        return [$concert, $member, $outing];
    }

    private function notifier(): OutingChangeNotifier
    {
        return self::getContainer()->get(OutingChangeNotifier::class);
    }
}
