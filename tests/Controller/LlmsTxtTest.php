<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Entity\City;
use App\EventSubscriber\LlmsTxtSubscriber;
use App\Factory\AdminZone1Factory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PageFactory;
use App\Factory\PlaceFactory;
use App\Tests\AppWebTestCase;
use App\Tests\Stats\CountsUpcomingEvents;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\save;

final class LlmsTxtTest extends AppWebTestCase
{
    use CountsUpcomingEvents;

    public function testListsTheAgendasAndThePages(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $toulouse->setParent(AdminZone1Factory::createOne(['name' => 'Haute-Garonne', 'country' => $france]));
        save($toulouse);
        $this->createEvents($toulouse, LlmsTxtSubscriber::CITY_MIN_EVENTS, '+2 days');
        // Less than a full agenda page of events to come: left out
        $this->createEvents(CityFactory::createOne(['name' => 'Muret', 'country' => $france]), LlmsTxtSubscriber::CITY_MIN_EVENTS - 1, '+2 days');
        // Only past events: its location page is noindex
        $this->createEvents(CityFactory::createOne(['name' => 'Albi', 'country' => $france]), LlmsTxtSubscriber::CITY_MIN_EVENTS, '-10 days');
        $this->createEvents(CityFactory::createOne(['name' => 'Liège', 'country' => CountryFactory::belgium()->create()]), 1, '+2 days');
        self::counter()->refresh();
        PageFactory::createOne(['title' => 'Est-ce que le handpan est illégal ?', 'metaDescription' => "Le handpan n'est pas illégal."]);

        $client->request('GET', '/llms.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $content = $client->getInternalResponse()->getContent();

        self::assertStringStartsWith("# By Night\n\n> Agenda des sorties partout en France : des milliers de concerts", $content);
        self::assertStringContainsString('By Night est un agenda de sorties indépendant, né en 2013 à Toulouse.', $content);
        // The static pages, from the "llms_txt" option of their route
        self::assertStringContainsString("## Pages\n\n- [À propos de By Night](https://by-night.test/a-propos): By Night est votre guide de sorties en France.", $content);
        self::assertStringContainsString('- [Accueil](https://by-night.test/): Découvrez des milliers', $content);
        self::assertStringContainsString('- [Recherche](https://by-night.test/recherche/): Trouvez des événements', $content);
        // The busiest first
        $events = LlmsTxtSubscriber::CITY_MIN_EVENTS;
        self::assertStringContainsString(\sprintf("## Agendas par pays\n\n- [France](https://by-night.test/france): %d sorties à venir\n- [Belgique](https://by-night.test/belgique): 1 sortie à venir\n", 2 * $events - 1), $content);
        self::assertStringContainsString(\sprintf("## Agendas par ville\n\n- [Toulouse](https://by-night.test/toulouse): Haute-Garonne, France : %d sorties à venir\n\n", $events), $content);
        self::assertStringNotContainsString('Muret', $content);
        self::assertStringNotContainsString('Albi', $content);
        self::assertStringNotContainsString('Liège', $content);
        self::assertStringContainsString("## Articles\n\n- [Est-ce que le handpan est illégal ?](https://by-night.test/p/est-ce-que-le-handpan-est-illegal): Le handpan n'est pas illégal.\n", $content);
        // Neither the events nor the venues
        self::assertStringNotContainsString('/soiree/', $content);
        self::assertStringNotContainsString('/sortir-a/', $content);
        // The legal pages come last, in the section an assistant may skip
        self::assertStringEndsWith("## Optional\n\n- [Politique de cookies](https://by-night.test/cookie): Les cookies utilisés par By Night, leur rôle, leur durée et comment accepter ou refuser ceux qui demandent votre accord.\n- [Mentions légales](https://by-night.test/mentions-legales): Mentions légales de By Night : éditeur, hébergeur, règles de publication, signalement de contenus et protection de vos données personnelles.\n", $content);
    }

    public function testLeavesTheAgendasOutWhileNothingIsToCome(): void
    {
        $client = self::createClient();
        $this->createEvents(CityFactory::toulouse()->create(), LlmsTxtSubscriber::CITY_MIN_EVENTS, '-10 days');
        self::counter()->refresh();

        $client->request('GET', '/llms.txt');

        self::assertResponseIsSuccessful();
        $content = $client->getInternalResponse()->getContent();
        self::assertStringContainsString('## Pages', $content);
        self::assertStringNotContainsString('## Agendas', $content);
        self::assertStringNotContainsString('## Articles', $content);
    }

    public function testThePagesAdvertiseIt(): void
    {
        $client = self::createClient();

        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('head link[rel="llms-txt"][href="https://by-night.test/llms.txt"]');
        // One of the links of the header: with a build in public/build, WebLink also lists the assets to preload there
        $links = preg_split('/,\s*(?=<)/', (string) $client->getResponse()->headers->get('Link'));
        self::assertContains('<https://by-night.test/llms.txt>; rel="llms-txt"', $links);
    }

    private function createEvents(City $city, int $count, string $date): void
    {
        $place = PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]);
        EventFactory::new()->withDates(new DateTimeImmutable($date))->many($count)->create(['place' => $place]);
    }
}
