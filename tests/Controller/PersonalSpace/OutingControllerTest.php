<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Mes sorties": the events to come a member goes to, soonest first.
 */
final class OutingControllerTest extends WebTestCase
{
    public function testAMemberSeesTheEventsToComeTheyGoToSoonestFirst(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        foreach (['Dans une semaine' => '+7 days', 'Demain' => 'tomorrow', 'Le mois dernier' => '-1 month'] as $name => $date) {
            UserEventFactory::createOne([
                'user' => $member,
                'event' => EventFactory::new()->withDates(new DateTimeImmutable($date))->create(['name' => $name]),
            ]);
        }
        // Another member's outing, and an event the member does not go to
        UserEventFactory::createOne(['event' => EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Pas la mienne'])]);
        EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => 'Sans moi']);
        $client->loginUser($member);

        $crawler = $client->request('GET', '/espace-perso/mes-sorties');

        self::assertResponseIsSuccessful();
        self::assertSame(['Demain', 'Dans une semaine'], $crawler->filter('#outings .card-title')->each(static fn ($title): string => trim($title->text())));
        // The past one stays on the public profile
        self::assertSelectorTextContains('body', 'Votre sortie passée reste');
        self::assertSelectorExists(\sprintf('a[href="/membres/%s--%d#passes"]', $member->getSlug(), $member->getId()));
    }

    public function testAJyVaisTakenBackLeavesTheOutings(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        foreach (['Finalement non' => [false, false], 'Peut-être' => [false, true], 'Oui' => [true, false]] as $name => [$going, $wish]) {
            UserEventFactory::createOne([
                'user' => $member,
                'event' => EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['name' => $name]),
                'going' => $going,
                'wish' => $wish,
            ]);
        }
        $client->loginUser($member);

        $crawler = $client->request('GET', '/espace-perso/mes-sorties');

        // The row of a "J'y vais" taken back stays (EventParticipationManager), neither going nor interested
        $names = $crawler->filter('#outings .card-title')->each(static fn ($title): string => trim($title->text()));
        sort($names);
        self::assertSame(['Oui', 'Peut-être'], $names);
    }

    public function testAMemberWithoutOutingsLearnsHowToAddOne(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/espace-perso/mes-sorties');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-title', 'Aucune sortie prévue');
        self::assertSelectorNotExists('#outings');
    }

    public function testTheOutingsNeedALogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/espace-perso/mes-sorties');

        self::assertResponseRedirects('/login');
    }

    public function testTheUserMenuLeadsToTheOutingsFirst(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/espace-perso/mes-sorties');

        self::assertSame('/espace-perso/mes-sorties', $crawler->filter('.nav-avatar .dropdown-menu a.dropdown-item')->first()->attr('href'));
    }
}
