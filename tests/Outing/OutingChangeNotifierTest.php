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
use App\Outing\OutingAlertRun;
use App\Outing\OutingChangeNotifier;
use App\Outing\OutingState;
use App\Repository\EventRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

use function Zenstruck\Foundry\Persistence\save;

final class OutingChangeNotifierTest extends AppKernelTestCase
{
    use ClockSensitiveTrait;

    #[RequiresPhpExtension('mjml')]
    public function testAMemberGoingToACancelledEventHearsOfIt(): void
    {
        [$event, $member, $outing] = $this->outing();
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        self::assertSame(1, $this->runAlerts()->getSent());

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
        self::assertNull($this->flag($event), 'told, so no longer flagged');
    }

    #[RequiresPhpExtension('mjml')]
    public function testAMovedEventSaysItsNewDatesAndVenue(): void
    {
        [$event] = $this->outing();
        $event
            ->setStartDate(new DateTimeImmutable('+20 days'))
            ->setEndDate(new DateTimeImmutable('+20 days'))
            ->setPlace(PlaceFactory::createOne(['name' => 'Le Zénith']));
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'Subject', "Changement de dates\u{a0}: Concert du samedi");
        self::assertEmailTextBodyContains($email, "Nouvelles dates\u{a0}: le ");
        self::assertEmailTextBodyContains($email, "Nouveau lieu\u{a0}: Le Zénith");
    }

    #[RequiresPhpExtension('mjml')]
    public function testAChangeMadeAfterTheRunStartedWaitsForTheNextRun(): void
    {
        [$event] = $this->outing();
        $event->setStatus(EventStatus::Cancelled);
        save($event);

        $this->runAlerts('-1 minute');

        self::assertEmailCount(0);
        self::assertNotNull($this->flag($event), 'left to the next run');

        $this->runAlerts('+1 minute');

        self::assertEmailCount(1);
    }

    #[RequiresPhpExtension('mjml')]
    public function testARunAgainSendsNothingTwice(): void
    {
        [$event] = $this->outing();
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();
        $second = $this->runAlerts();

        self::assertSame(0, $second->getSent());
        self::assertEmailCount(1);
    }

    #[RequiresPhpExtension('mjml')]
    public function testAChangeAfterTheDaysAlertIsToldByTheFirstRunOfTheNextDay(): void
    {
        [$event, , $outing] = $this->outing();
        $outing->setAlertedAt(new DateTimeImmutable());
        save($outing);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
        // Still to tell
        self::assertNull(UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
        self::assertNotNull($this->flag($event), 'kept flagged for tomorrow');

        $this->runAlerts('tomorrow 08:00');

        self::assertEmailCount(1);
        self::assertNull($this->flag($event));
    }

    /**
     * A run clears the flags of each page before reading the next: the cursor reads every event once all the same,
     * those it keeps flagged included.
     */
    public function testTheFlaggedEventsAreReadOnceWhileARunClearsTheirFlags(): void
    {
        $ids = array_map(
            static fn (Event $event): int => (int) $event->getId(),
            EventFactory::createMany(5, ['flaggedForAlertAt' => new DateTimeImmutable('2026-10-24 09:00')]),
        );
        $repository = self::getContainer()->get(EventRepository::class);
        $startedAt = new DateTimeImmutable('2026-10-24 10:00');

        $read = [];
        foreach ($repository->findFlaggedForAlert($startedAt, 2)->getChunkResults() as $events) {
            $page = array_values(array_intersect(array_column($events, 'id'), $ids));
            $read = [...$read, ...$page];
            // The first of each page stays flagged, as a follower still to tell would keep it
            $repository->clearAlertFlags(\array_slice($page, 1), $startedAt);
        }

        self::assertSame($ids, $read);
    }

    /**
     * The flag keeps whole seconds: a change made in the second the run started may come after the run read the event.
     */
    public function testAChangeInTheSecondTheRunStartedWaitsForTheNextRun(): void
    {
        $event = EventFactory::createOne(['flaggedForAlertAt' => new DateTimeImmutable('2026-10-24 10:00:00')]);
        $repository = self::getContainer()->get(EventRepository::class);
        $startedAt = new DateTimeImmutable('2026-10-24 10:00:00.700');

        self::assertNotContains($event->getId(), array_column([...$repository->findFlaggedForAlert($startedAt, 500)], 'id'));
        $repository->clearAlertFlags([(int) $event->getId()], $startedAt);
        self::assertNotNull($this->flag($event));
    }

    public function testAChangeMadeWhileTheRunReadTheEventKeepsItsFlag(): void
    {
        $event = EventFactory::createOne(['flaggedForAlertAt' => new DateTimeImmutable('2026-10-24 10:05')]);

        // The run read the events flagged by 10:00, then this one changed again at 10:05
        self::getContainer()->get(EventRepository::class)->clearAlertFlags([(int) $event->getId()], new DateTimeImmutable('2026-10-24 10:00'));

        self::assertEquals(new DateTimeImmutable('2026-10-24 10:05'), $this->flag($event));
    }

    public function testAnEventNobodyFollowsIsNoLongerFlagged(): void
    {
        $event = EventFactory::createOne();
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertNull($this->flag($event));
    }

    public function testNothingChangedSendsNothing(): void
    {
        [$event] = $this->outing();
        $event->setName('Concert du samedi soir');
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
    }

    public function testTheAuthorOfTheEventHearsNothing(): void
    {
        $author = UserFactory::createOne();
        [$event] = $this->outing(member: $author, event: ['user' => $author]);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

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
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
    }

    public function testAMemberWhoTurnedTheAlertsOffHearsNothingButKnowsTheEventAsItIs(): void
    {
        [$event, , $outing] = $this->outing(member: UserFactory::createOne(['outingAlerts' => false]));
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
        // Turned on again, the alerts would not tell this cancellation
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
    }

    public function testAMemberWhoseAddressWouldNotReachThemHearsNothingButKnowsTheEventAsItIs(): void
    {
        [$event, , $outing] = $this->outing(member: UserFactory::createOne(['email' => 'ada@facebook.fr']));
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
        self::assertNull($this->flag($event));
    }

    /**
     * The mailer refuses a legacy address ("First Last@domain"): the run goes on with the next followers, and the
     * event is told all the same, or every run would stop at this follower.
     */
    #[RequiresPhpExtension('mjml')]
    public function testAnInvalidAddressIsSkippedAndTheRunGoesOn(): void
    {
        [$event, , $skipped] = $this->outing(member: UserFactory::createOne(['email' => 'First Last@example.com']));
        $told = UserEventFactory::createOne(['event' => $event, 'going' => true, 'wish' => false, 'knownState' => OutingState::fromEvent($event)]);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $run = $this->runAlerts();

        self::assertSame(1, $run->getSent());
        self::assertSame([], $run->getFailures());
        self::assertCount(1, $run->getSkipped());
        self::assertSame('First Last@example.com', $run->getSkipped()[0]['email']);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', (string) $told->getUser()?->getEmail());
        $skipped = UserEventFactory::find(['id' => $skipped->getId()]);
        self::assertSame('cancelled', $skipped->getKnownState()?->status, 'Not tried again');
        self::assertNull($skipped->getAlertedAt());
        self::assertNull($this->flag($event));
    }

    public function testARowOlderThanTheAlertsLearnsTheEventWithoutAnAlert(): void
    {
        [$event, , $outing] = $this->outing(known: false);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
    }

    public function testAnEventOverHasNothingLeftToTell(): void
    {
        [$event] = $this->outing(day: '-3 days');
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
    }

    #[RequiresPhpExtension('mjml')]
    public function testAnEventItsAuthorHidIsToldOncePublishedAgain(): void
    {
        [$event, , $outing] = $this->outing();
        $event->setDraft(true)->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
        self::assertNull(UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);

        $event = EventFactory::find(['id' => $event->getId()]);
        $event->setDraft(false);
        $this->changed($event);
        $this->runAlerts();

        self::assertEmailCount(1);
        self::assertSame('cancelled', UserEventFactory::find(['id' => $outing->getId()])->getKnownState()?->status);
    }

    public function testAMemberTakingBackTheirJyVaisHearsNothing(): void
    {
        [$event] = $this->outing(going: false);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
    }

    public function testADuplicateTellsNothingItsPageLeadsToItsCanonical(): void
    {
        [$event] = $this->outing(event: ['duplicateOf' => EventFactory::createOne()]);
        $event->setStatus(EventStatus::Cancelled);
        $this->changed($event);

        $this->runAlerts();

        self::assertEmailCount(0);
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

    /**
     * Saves the change as made a minute before the run.
     */
    private function changed(Event $event): void
    {
        self::mockTime('-1 minute');
        save($event);
        self::mockTime(false);
    }

    private function runAlerts(string $at = 'now'): OutingAlertRun
    {
        return self::getContainer()->get(OutingChangeNotifier::class)->sendFlagged(new DateTimeImmutable($at));
    }

    private function flag(Event $event): ?DateTimeImmutable
    {
        return EventFactory::find(['id' => $event->getId()])->getFlaggedForAlertAt();
    }
}
