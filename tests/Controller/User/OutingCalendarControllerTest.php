<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Entity\User;
use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\save;

/**
 * A member's outings as the calendar their app subscribes to: read without a session, at a secret address.
 */
final class OutingCalendarControllerTest extends AppWebTestCase
{
    public function testTheCalendarListsTheOutingsOfItsMember(): void
    {
        $client = self::createClient();
        $member = $this->memberWithCalendar();
        foreach (['Concert demain' => [true, false, 'tomorrow'], 'Expo peut-être' => [false, true, '+3 days'], 'Plus du tout' => [false, false, '+5 days'], 'Il y a deux mois' => [true, false, '-2 months'], 'La semaine dernière' => [true, false, '-7 days']] as $name => [$going, $wish, $date]) {
            UserEventFactory::createOne([
                'user' => $member,
                'event' => EventFactory::new()->withDates(new DateTimeImmutable($date))->create(['name' => $name]),
                'going' => $going,
                'wish' => $wish,
            ]);
        }
        // Another member's outing, and a draft
        UserEventFactory::createOne(['event' => EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Pas la mienne'])]);
        UserEventFactory::createOne(['user' => $member, 'event' => EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Brouillon', 'draft' => true])]);

        $client->request('GET', '/calendrier/' . $member->getCalendarToken() . '.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $calendar = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('X-WR-CALNAME:Mes sorties By Night', $calendar);
        self::assertStringContainsString('X-PUBLISHED-TTL:PT3H', $calendar);
        self::assertStringContainsString('SUMMARY:Concert demain', $calendar);
        self::assertStringContainsString('SUMMARY:Expo peut-être', $calendar);
        // A week ago stays, the app would otherwise drop it; two months ago is gone
        self::assertStringContainsString('SUMMARY:La semaine dernière', $calendar);
        self::assertStringNotContainsString('Il y a deux mois', $calendar);
        self::assertStringNotContainsString('Plus du tout', $calendar);
        self::assertStringNotContainsString('Pas la mienne', $calendar);
        self::assertStringNotContainsString('Brouillon', $calendar);
        self::assertSame(['CONFIRMED', 'TENTATIVE', 'CONFIRMED'], $this->statuses($calendar, ['La semaine dernière', 'Expo peut-être', 'Concert demain']));
    }

    public function testACancelledOutingStaysInTheCalendarAsCancelled(): void
    {
        $client = self::createClient();
        $member = $this->memberWithCalendar();
        UserEventFactory::createOne([
            'user' => $member,
            'event' => EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Annulé', 'status' => EventStatus::Cancelled]),
        ]);

        $client->request('GET', '/calendrier/' . $member->getCalendarToken() . '.ics');

        self::assertSame(['CANCELLED'], $this->statuses((string) $client->getResponse()->getContent(), ['Annulé']));
    }

    public function testTheCalendarOpensNoSessionAndStaysOutOfSearchEnginesAndSharedCaches(): void
    {
        $client = self::createClient();
        $member = $this->memberWithCalendar();

        $client->request('GET', '/calendrier/' . $member->getCalendarToken() . '.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('max-age=900', $cacheControl);
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testAnUnknownAddressIsNotFound(): void
    {
        $client = self::createClient();
        $this->memberWithCalendar();

        $client->request('GET', '/calendrier/' . str_repeat('a', 32) . '.ics');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFormerAddressOfARenewedCalendarIsNotFound(): void
    {
        $client = self::createClient();
        $member = $this->memberWithCalendar();
        $former = (string) $member->getCalendarToken();
        $member->renewCalendarToken();
        save($member);

        $client->request('GET', '/calendrier/' . $former . '.ics');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/calendrier/' . $member->getCalendarToken() . '.ics');
        self::assertResponseIsSuccessful();
    }

    public function testTheCalendarOfADisabledMemberIsNotFound(): void
    {
        $client = self::createClient();
        $member = $this->memberWithCalendar();
        $member->setEnabled(false);
        save($member);

        $client->request('GET', '/calendrier/' . $member->getCalendarToken() . '.ics');

        self::assertResponseStatusCodeSame(404);
    }

    private function memberWithCalendar(): User
    {
        $member = UserFactory::createOne();
        $member->renewCalendarToken();
        save($member);

        return $member;
    }

    /**
     * @param list<string> $names the summaries of the entries, in the order of the statuses returned
     *
     * @return list<string> the status of each entry
     */
    private function statuses(string $calendar, array $names): array
    {
        $statuses = [];
        foreach ($names as $name) {
            // The STATUS of the VEVENT holding the SUMMARY, whichever comes first
            $entry = preg_match('/BEGIN:VEVENT((?:(?!END:VEVENT).)*SUMMARY:' . preg_quote($name, '/') . '\r?\n(?:(?!END:VEVENT).)*)END:VEVENT/s', $calendar, $matches);
            self::assertSame(1, $entry, \sprintf('"%s" is in the calendar', $name));
            self::assertSame(1, preg_match('/^STATUS:([A-Z]+)/m', $matches[1], $status));
            $statuses[] = $status[1];
        }

        return $statuses;
    }
}
