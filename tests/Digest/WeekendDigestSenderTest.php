<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Digest;

use App\Digest\WeekendDigestSender;
use App\Digest\WeekendDigestUnsubscribeUrl;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Manager\MailerManager;
use App\Message\SendWeekendDigest;
use App\MessageHandler\SendWeekendDigestHandler;
use App\Repository\UserRepository;
use App\Search\DateRange;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Mime\Email;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * The weekend selection of a member: the e-mail renders MJML, which needs ext-mjml.
 */
#[RequiresPhpExtension('mjml')]
final class WeekendDigestSenderTest extends AppKernelTestCase
{
    use StubsWeekendSearch;

    private City $toulouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->toulouse = CityFactory::toulouse()->create();
    }

    public function testTheMemberGetsTheSelectionOfTheirCity(): void
    {
        $user = $this->subscriber();
        $events = $this->events(3);

        self::assertTrue($this->sender($events)->send($user, $this->weekend()));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', (string) $user->getEmail());
        self::assertSame("Ce week-end à Toulouse\u{a0}: 3 sorties à ne pas manquer", $email->getSubject());
        self::assertEmailHtmlBodyContains($email, 'Concert 1');
        // The city's weekend page itself, not the agenda's shortcut that redirects to it, tagged with the week sent
        $week = WeekendDigestSender::weekOf($this->weekend());
        self::assertEmailHtmlBodyContains($email, 'https://by-night.test/toulouse/ce-week-end?utm_source=weekend-digest');
        self::assertEmailTextBodyContains($email, 'https://by-night.test/toulouse/ce-week-end?utm_source=weekend-digest&utm_medium=email&utm_campaign=' . $week . '&utm_content=cta');
        self::assertEmailTextBodyContains($email, 'utm_campaign=' . $week . '&utm_content=event');
        self::assertEmailTextBodyContains($email, 'Concert 3');

        $unsubscribeUrl = self::getContainer()->get(WeekendDigestUnsubscribeUrl::class)->generate($user);
        self::assertEmailHeaderSame($email, 'List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        self::assertEmailHeaderSame($email, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        self::assertSame(WeekendDigestSender::weekOf($this->weekend()), refresh($user)->getWeekendDigestSentWeek());
    }

    public function testAMemberGetsOneSelectionAWeek(): void
    {
        $user = $this->subscriber();
        $sender = $this->sender($this->events(3));

        self::assertTrue($sender->send($user, $this->weekend()));
        self::assertFalse($sender->send($user, $this->weekend()), 'A second run of the week');

        self::assertEmailCount(1);
    }

    public function testAWeekendWithTooLittleSendsNothing(): void
    {
        $user = $this->subscriber();

        self::assertFalse($this->sender($this->events(WeekendDigestSender::MIN_EVENTS - 1))->send($user, $this->weekend()));

        self::assertEmailCount(0);
        self::assertNull(refresh($user)->getWeekendDigestSentWeek(), 'Next run may find more');
    }

    public function testOnlyTheMembersWhoAskedForItGetIt(): void
    {
        $sender = $this->sender($this->events(3));

        self::assertFalse($sender->send(UserFactory::createOne(['city' => $this->toulouse]), $this->weekend()), 'Never asked');
        self::assertFalse($sender->send($this->subscriber(['enabled' => false]), $this->weekend()), 'Account disabled');

        self::assertEmailCount(0);
    }

    public function testAMessageOfAWeekendOverSendsNothing(): void
    {
        $user = $this->subscriber();
        $handler = new SendWeekendDigestHandler(self::getContainer()->get(UserRepository::class), $this->sender($this->events(3)));

        $handler(new SendWeekendDigest((int) $user->getId(), new DateTimeImmutable('-9 days'), new DateTimeImmutable('-7 days')));
        self::assertEmailCount(0);

        $weekend = $this->weekend();
        $handler(new SendWeekendDigest((int) $user->getId(), $weekend->from, $weekend->to));
        self::assertEmailCount(1);
    }

    /**
     * @param list<Event> $found what the weekend search finds
     */
    private function sender(array $found): WeekendDigestSender
    {
        $container = self::getContainer();

        return new WeekendDigestSender(
            $this->weekendSelector($found),
            $container->get(MailerManager::class),
            $container->get(WeekendDigestUnsubscribeUrl::class),
            $container->get(EntityManagerInterface::class),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function subscriber(array $attributes = []): User
    {
        return UserFactory::createOne(['city' => $this->toulouse, 'weekendDigest' => true] + $attributes);
    }

    /**
     * @return list<Event>
     */
    private function events(int $count): array
    {
        $place = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $this->toulouse, 'country' => $this->toulouse->getCountry()]);

        return array_map(
            fn (int $i): Event => EventFactory::new()->withDates($this->weekend()->from)->create(['name' => 'Concert ' . $i, 'place' => $place]),
            range(1, $count),
        );
    }

    /**
     * The next weekend, still to come: a message for a weekend over sends nothing.
     */
    private function weekend(): DateRange
    {
        $friday = new DateTimeImmutable('friday next week');

        return new DateRange($friday, $friday->modify('+2 days'));
    }
}
