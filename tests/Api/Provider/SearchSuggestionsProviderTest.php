<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use App\Api\ApiResource\SearchResult;
use App\Api\Provider\SearchSuggestionsProvider;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

/**
 * GET /api/search/suggestions fills the search panel before a word is typed: around the city of the page when it
 * knows one, France otherwise.
 */
final class SearchSuggestionsProviderTest extends AppKernelTestCase
{
    public function testAroundACityTheCategoriesOfItsEventsToComeThenItsTopEventsOfTheWeek(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry(), 'name' => 'Le Bikini']);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        $theatre = TagFactory::createOne(['name' => 'Théâtre']);

        $tonight = $this->event('Soirée électro', $place, $concert, 'today', 'today');
        $this->event('Concert de la semaine prochaine', $place, $concert, '+10 days', '+10 days');
        $this->event('Pièce du mois prochain', $place, $theatre, '+1 month', '+1 month');
        // Over, or elsewhere: not counted
        $this->event('Exposition passée', $place, TagFactory::createOne(['name' => 'Exposition']), '-1 month', '-1 month');
        $paris = CityFactory::createOne(['name' => 'Paris', 'country' => $toulouse->getCountry()]);
        $this->event('Opéra à Paris', PlaceFactory::createOne(['city' => $paris, 'country' => $toulouse->getCountry()]), TagFactory::createOne(['name' => 'Opéra']), '+1 day', '+1 day');

        self::assertSame([
            ['tags', 'Catégories', 'Concert', '2', null, \sprintf('/toulouse/agenda/tag/%s--%d', $concert->getSlug(), $concert->getId())],
            ['tags', 'Catégories', 'Théâtre', '1', null, \sprintf('/toulouse/agenda/tag/%s--%d', $theatre->getSlug(), $theatre->getId())],
            ['events', 'Cette semaine à Toulouse', 'Soirée électro', 'Le Bikini', new DateTimeImmutable('today')->format('d/m/Y'), \sprintf('/toulouse/soiree/%s--%d', $tonight->getSlug(), $tonight->getId())],
        ], $this->summarize($this->suggest('toulouse')));
    }

    public function testACityWithoutEventThisWeekShowsItsEventsToCome(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $this->event('Pièce du mois prochain', $place, TagFactory::createOne(), '+1 month', '+1 month');

        $events = array_values(array_filter($this->suggest('toulouse'), static fn (SearchResult $result): bool => 'events' === $result->type));

        self::assertSame(['Prochainement à Toulouse'], array_unique(array_map(static fn (SearchResult $result): string => $result->category, $events)));
    }

    public function testAnEventRunningSinceMonthsSaysUntilWhen(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $this->event('Exposition au long cours', $place, TagFactory::createOne(), '-2 months', 'today');

        $events = array_values(array_filter($this->suggest('toulouse'), static fn (SearchResult $result): bool => 'events' === $result->type));

        self::assertSame('Jusqu\'au ' . new DateTimeImmutable('today')->format('d/m/Y'), $events[0]->description);
    }

    public function testWithoutACityTheBiggestCitiesOfFranceThenItsTopEventsOfTheWeek(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        CityFactory::createOne(['name' => 'Paris', 'population' => 2_100_000, 'country' => $toulouse->getCountry()]);
        CityFactory::createOne(['name' => 'Albi', 'population' => 49_000, 'country' => $toulouse->getCountry()]);
        // Not in France
        CityFactory::createOne(['name' => 'Bruxelles', 'population' => 1_200_000, 'country' => CountryFactory::new(['id' => 'BE'])]);
        $this->event('Soirée électro', PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]), TagFactory::createOne(), 'today', 'today');

        // No city, or one the page names but which is gone
        foreach ([null, 'nowhere-at-all'] as $city) {
            $suggestions = $this->summarize($this->suggest($city));

            self::assertSame([
                ['cities', 'Villes', 'Paris', '', null, '/paris/'],
                ['cities', 'Villes', 'Toulouse', '', null, '/toulouse/'],
                ['cities', 'Villes', 'Albi', '', null, '/albi/'],
            ], \array_slice($suggestions, 0, 3));
            self::assertSame(['events', 'Cette semaine en France', 'Soirée électro'], \array_slice($suggestions[3], 0, 3));
        }
    }

    private function event(string $name, Place $place, Tag $category, string $start, string $end): Event
    {
        return EventFactory::new()
            ->withDates(new DateTimeImmutable($start), new DateTimeImmutable($end))
            ->create(['name' => $name, 'place' => $place, 'category' => $category]);
    }

    /**
     * @return list<SearchResult>
     */
    private function suggest(?string $city): array
    {
        return self::getContainer()->get(SearchSuggestionsProvider::class)
            ->provide(new GetCollection(), [], ['filters' => null === $city ? [] : ['city' => $city]]);
    }

    /**
     * @param list<SearchResult> $results
     *
     * @return list<array{string, string, string, string, ?string, string}>
     */
    private function summarize(array $results): array
    {
        return array_map(static fn (SearchResult $result): array => [$result->type, $result->category, $result->label, $result->shortDescription, $result->description, $result->url], $results);
    }
}
