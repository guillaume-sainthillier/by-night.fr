<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Publisher;

use App\Entity\Event;
use App\Entity\Publisher;
use App\Factory\EventFactory;
use App\Factory\PublisherFactory;
use App\Factory\PublisherLegacySlugFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

use function Zenstruck\Foundry\Persistence\delete;

/**
 * A publisher's events outside its page: the calendar its public subscribes to, the widget its website frames.
 */
final class PublisherFeedControllerTest extends AppWebTestCase
{
    public function testTheCalendarListsThePublishersEventsOnline(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        $upcoming = $this->createEvent($publisher, '+10 days', ['name' => 'Concert de jazz']);
        $this->createEvent($publisher, '-7 days', ['name' => 'La semaine dernière']);
        $this->createEvent($publisher, '-2 months', ['name' => 'Il y a deux mois']);
        $this->createEvent($publisher, '+10 days', ['name' => 'Brouillon', 'draft' => true]);
        $this->createEvent($publisher, '+10 days', ['name' => 'Doublon', 'duplicateOf' => $upcoming]);
        $this->createEvent(PublisherFactory::createOne(), '+10 days', ['name' => 'Concert ailleurs']);

        $client->request('GET', '/organisateurs/le-bikini/calendrier.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $calendar = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('X-WR-CALNAME:Le Bikini · By Night', $calendar);
        self::assertStringContainsString('SUMMARY:Concert de jazz', $calendar);
        // A week ago stays, the app would otherwise drop it; two months ago is gone
        self::assertStringContainsString('SUMMARY:La semaine dernière', $calendar);
        self::assertStringNotContainsString('Il y a deux mois', $calendar);
        self::assertStringNotContainsString('Brouillon', $calendar);
        self::assertStringNotContainsString('Doublon', $calendar);
        self::assertStringNotContainsString('Concert ailleurs', $calendar);
    }

    public function testTheCalendarIsSharedByCloudflareAndOpensNoSession(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);

        $client->request('GET', '/organisateurs/le-bikini/calendrier.ics');

        self::assertResponseHeaderSame('Cache-Control', 'max-age=900, public, s-maxage=3600');
        self::assertResponseHeaderSame('Cache-Tag', 'publisher-' . $publisher->getId());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertResponseHeaderSame('Content-Disposition', 'inline; filename=le-bikini.ics');
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testTheWidgetListsTheNextEventsAndOpensThemOutsideTheFrame(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        $concert = $this->createEvent($publisher, '+3 days', ['name' => 'Concert de jazz']);
        $this->createEvent($publisher, '+5 days', ['name' => 'Concert de rock']);
        $this->createEvent($publisher, '-3 days', ['name' => 'Déjà passé']);

        $crawler = $client->request('GET', '/organisateurs/le-bikini/widget');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Security-Policy', 'frame-ancestors *');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSame(['Concert de jazz', 'Concert de rock'], $crawler->filter('.list-group-item strong')->each(static fn ($name): string => trim($name->text())));
        self::assertSelectorExists('base[target="_blank"]');
        self::assertSelectorExists(\sprintf('a[href="https://by-night.test/%s/soiree/%s--%d"]', $concert->getLocationSlug(), $concert->getSlug(), $concert->getId()));
        self::assertSelectorExists('a[href="https://by-night.test/organisateurs/le-bikini"]');
        self::assertSelectorExists('html[data-bs-theme="light"]');
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testTheWidgetListsAsManyEventsAsAskedInTheThemeAsked(): void
    {
        $client = self::createClient();
        $publisher = PublisherFactory::createOne(['name' => 'Le Bikini']);
        for ($i = 1; $i <= 3; ++$i) {
            $this->createEvent($publisher, \sprintf('+%d days', $i), ['name' => 'Concert ' . $i]);
        }

        $crawler = $client->request('GET', '/organisateurs/le-bikini/widget?limit=2&theme=dark');

        self::assertCount(2, $crawler->filter('.list-group-item'));
        self::assertSelectorExists('html[data-bs-theme="dark"]');
    }

    public function testTheWidgetOfAPublisherWithoutEventsSaysSo(): void
    {
        $client = self::createClient();
        PublisherFactory::createOne(['name' => 'Le Bikini']);

        $client->request('GET', '/organisateurs/le-bikini/widget');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card-body', 'Aucun événement à venir');
    }

    public function testTheFeedsOfADeletedPublisherLeadToTheOneThatTookItsEvents(): void
    {
        $client = self::createClient();
        PublisherLegacySlugFactory::createOne(['publisher' => PublisherFactory::createOne(['name' => 'Le Bikini']), 'slug' => 'bikini-toulouse']);

        $client->request('GET', '/organisateurs/bikini-toulouse/widget?limit=3');
        self::assertResponseRedirects('/organisateurs/le-bikini/widget?limit=3', Response::HTTP_MOVED_PERMANENTLY);

        $client->request('GET', '/organisateurs/bikini-toulouse/calendrier.ics');
        self::assertResponseRedirects('/organisateurs/le-bikini/calendrier.ics', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheFeedsOfADeletedPublisherAreGone(): void
    {
        $client = self::createClient();
        delete(PublisherFactory::createOne(['slug' => 'le-collectif']));

        $client->request('GET', '/organisateurs/le-collectif/calendrier.ics');

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(Publisher $publisher, string $date, array $attributes): Event
    {
        return EventFactory::new()->withDates(new DateTimeImmutable($date))->create(['publisher' => $publisher, ...$attributes]);
    }
}
