<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Follow;

use App\Entity\Event;
use App\Entity\Follow;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use App\Factory\EventFactory;
use App\Factory\FollowFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Follow\FollowAlertSender;
use App\Follow\FollowAlertUnsubscribeUrl;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Mime\Email;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * The new events of the publishers and the venues a member follows: the e-mail renders MJML, which needs ext-mjml.
 */
#[RequiresPhpExtension('mjml')]
final class FollowAlertSenderTest extends AppKernelTestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('today 18:00');
    }

    public function testTheMemberHearsOfTheNewEventsOfAPublisherTheyFollow(): void
    {
        $member = UserFactory::createOne();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        $this->follow($member, $publisher, '-10 days');
        $this->createEvent(['name' => 'Concert de jazz', 'publisher' => $publisher, 'publishedAt' => $this->now->modify('-2 hours')]);

        self::assertTrue($this->sender()->send($member, $this->now));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', (string) $member->getEmail());
        self::assertSame('Un nouvel événement chez ceux que vous suivez', $email->getSubject());
        self::assertEmailHtmlBodyContains($email, 'Concert de jazz');
        self::assertEmailTextBodyContains($email, 'utm_source=follow-alert&utm_medium=email&utm_campaign=' . $this->now->format('Y-m-d') . '&utm_content=event');
        $unsubscribeUrl = self::getContainer()->get(FollowAlertUnsubscribeUrl::class)->generate($member);
        self::assertEmailHeaderSame($email, 'List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        self::assertEmailHeaderSame($email, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        self::assertEquals($this->now, refresh($member)->getFollowAlertsSentAt());
    }

    public function testTheMemberHearsOfTheNewEventsOfAVenueTheyFollow(): void
    {
        $member = UserFactory::createOne();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini']);
        $this->follow($member, $bikini, '-10 days');
        $this->createEvent(['name' => 'Concert de jazz', 'place' => $bikini, 'publishedAt' => $this->now->modify('-2 hours')]);
        $this->createEvent(['name' => 'Concert de rock', 'place' => $bikini, 'publishedAt' => $this->now->modify('-1 hour')]);

        self::assertTrue($this->sender()->send($member, $this->now));

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('2 nouveaux événements chez ceux que vous suivez', $email->getSubject());
    }

    public function testWhatIsNoNewsIsNotSent(): void
    {
        $member = UserFactory::createOne(['followAlertsSentAt' => $this->now->modify('-1 day')]);
        $publisher = PublisherFactory::createOne();
        $this->follow($member, $publisher, '-10 days');
        // Told yesterday, online before they followed, a draft, folded into another, already over, another publisher's
        $this->createEvent(['publisher' => $publisher, 'publishedAt' => $this->now->modify('-2 days')]);
        $this->createEvent(['publisher' => $publisher, 'draft' => true, 'publishedAt' => $this->now->modify('-1 hour')]);
        $canonical = $this->createEvent(['publisher' => PublisherFactory::createOne(), 'publishedAt' => $this->now->modify('-1 hour')]);
        $this->createEvent(['publisher' => $publisher, 'duplicateOf' => $canonical, 'publishedAt' => $this->now->modify('-1 hour')]);
        $this->createEvent(['publisher' => $publisher, 'startDate' => $this->now->modify('-3 days'), 'endDate' => $this->now->modify('-2 days'), 'publishedAt' => $this->now->modify('-1 hour')]);

        $later = PublisherFactory::createOne();
        $this->follow($member, $later, '-1 hour');
        $this->createEvent(['publisher' => $later, 'publishedAt' => $this->now->modify('-3 hours')]);

        self::assertFalse($this->sender()->send($member, $this->now));

        self::assertEmailCount(0);
        self::assertEquals($this->now, refresh($member)->getFollowAlertsSentAt(), 'The next e-mail starts from this run');
    }

    public function testAMemberWhoTurnedThemOffHearsNothing(): void
    {
        $member = UserFactory::createOne(['followAlerts' => false]);
        $publisher = PublisherFactory::createOne();
        $this->follow($member, $publisher, '-10 days');
        $this->createEvent(['publisher' => $publisher, 'publishedAt' => $this->now->modify('-1 hour')]);

        self::assertFalse($this->sender()->send($member, $this->now));

        self::assertEmailCount(0);
    }

    public function testAnEMailListsTheFirstEventsAndCountsTheOthers(): void
    {
        $member = UserFactory::createOne();
        $publisher = PublisherFactory::createOne();
        $this->follow($member, $publisher, '-10 days');
        for ($i = 1; $i <= FollowAlertSender::MAX_EVENTS + 2; ++$i) {
            $this->createEvent(['name' => 'Concert ' . $i, 'publisher' => $publisher, 'publishedAt' => $this->now->modify('-1 hour')]);
        }

        self::assertTrue($this->sender()->send($member, $this->now));

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(\sprintf('%d nouveaux événements chez ceux que vous suivez', FollowAlertSender::MAX_EVENTS + 2), $email->getSubject());
        self::assertEmailTextBodyContains($email, 'Et 2 autres sur leurs pages.');
    }

    private function sender(): FollowAlertSender
    {
        return self::getContainer()->get(FollowAlertSender::class);
    }

    private function follow(User $member, Publisher|Place $target, string $since): Follow
    {
        $follow = FollowFactory::createOne(['user' => $member, ...($target instanceof Place ? ['place' => $target, 'publisher' => null] : ['publisher' => $target])]);
        $follow->setCreatedAt($this->now->modify($since));
        save($follow);

        return $follow;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(array $attributes): Event
    {
        return EventFactory::new()->withDates($this->now->modify('+5 days'))->create($attributes);
    }
}
