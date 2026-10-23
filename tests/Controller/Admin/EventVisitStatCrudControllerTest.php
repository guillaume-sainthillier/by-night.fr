<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\EventFactory;
use App\Factory\EventVisitStatFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class EventVisitStatCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsTheRowsOfEachEventAndDay(): void
    {
        $client = $this->createAdminClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('2026-10-22'), 'visitors' => 41, 'ticketClicks' => 5]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('2026-08-01'), 'visitors' => 3, 'ticketClicks' => 0, 'fromGoogleAnalytics' => true]);

        $crawler = $client->request('GET', '/_administration/event-visit-stat');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Concert de jazz');
        // Newest day first
        $rows = $crawler->filter('table tbody tr');
        self::assertCount(2, $rows);
        self::assertStringContainsString('41', $rows->eq(0)->text());
        self::assertStringContainsString('3', $rows->eq(1)->text());
    }

    public function testTheRowsAreReadOnly(): void
    {
        $client = $this->createAdminClient();
        $stat = EventVisitStatFactory::createOne();

        $crawler = $client->request('GET', '/_administration/event-visit-stat');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a[href*="/event-visit-stat/new"]'));
        self::assertCount(0, $crawler->filter(\sprintf('a[href*="/event-visit-stat/%d/edit"]', $stat->getId())));

        $client->request('GET', \sprintf('/_administration/event-visit-stat/%d/edit', $stat->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testADayIsShownInDetail(): void
    {
        $client = $this->createAdminClient();
        $stat = EventVisitStatFactory::createOne(['visitors' => 17, 'ticketClicks' => 2]);

        $client->request('GET', \sprintf('/_administration/event-visit-stat/%d', $stat->getId()));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.field-group, .field-integer, dd, td', '17');
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
