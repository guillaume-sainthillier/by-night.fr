<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Enum\AgendaType;
use App\Search\AgendaFacets;
use App\Search\DateRange;
use App\Search\SearchEvent;
use DateTimeImmutable;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\Filter;
use Elastica\Aggregation\Filters;
use Elastica\Aggregation\Terms as TermsAggregation;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\GeoDistance;
use Elastica\Query\MatchAll;
use Elastica\Query\MatchPhrase;
use Elastica\Query\MultiMatch;
use Elastica\Query\Nested;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;

final class EventElasticaRepository extends Repository
{
    public function findWithSearch(SearchEvent $search): AdapterInterface
    {
        return new FantaPaginatorAdapter($this->createPaginatorAdapter($this->createSearchQuery($search)));
    }

    /**
     * The agenda query: location, then sessions (one must overlap the requested
     * window), then type, text and tags. Sorted by the soonest-ending session in the window,
     * so an event is listed by its next date rather than by its overall range,
     * or by relevance when a term is searched.
     */
    public function createSearchQuery(SearchEvent $search): Query
    {
        $mainQuery = new BoolQuery();
        $location = $this->createPlaceFilter($search) ?? $this->createAreaFilter($search);
        if (null !== $location) {
            $mainQuery->addFilter($location);
        }

        // One entry per session in the index: the event is listed when one of its
        // sessions overlaps the requested window, not when its overall range does.
        // The same condition drives the sort below.
        $sessionFilter = $this->createSessionFilter($search->getDateRange());
        $mainQuery->addFilter(new Nested()->setPath('sessions')->setQuery($sessionFilter));

        // A filter, not a scored clause: a type page lists its events by date, as the agenda does
        $typeFilter = $this->createTypeFilter($search->getType());
        if (null !== $typeFilter) {
            $mainQuery->addFilter($typeFilter);
        }

        $textQuery = $this->createTextQuery($search);
        if (null !== $textQuery) {
            $mainQuery->addMust($textQuery);
        }

        $tagFilter = $this->createTagFilter($search);
        if (null !== $tagFilter) {
            $mainQuery->addFilter($tagFilter);
        }

        // Construction de la requête finale
        $finalQuery = Query::create($mainQuery);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields
        // The count stops at 10 000 otherwise ("sur 10 000" on /c--france/agenda). The pages still stop at the result
        // window (ResultWindow); counting the rest took no measurable time, the nested sort already visits every hit
        $finalQuery->setTrackTotalHits(true);
        if (null === $textQuery) {
            // Soonest-ending session in the window first: a one-day session sorts by
            // its day, an exhibition still running by its last day, as the range did.
            $finalQuery->addSort(['sessions.endAt' => [
                'order' => 'asc',
                'mode' => 'min',
                'nested' => ['path' => 'sessions', 'filter' => $sessionFilter->toArray()],
            ]]);

            $city = $search->getLocation()?->isCity() ? $search->getLocation()->getCity() : null;
            if (null !== $city && [] === $search->getLieux()) {
                $finalQuery->addSort(['_geo_distance' => [
                    'place.city.location' => $city->getLocation(),
                    'order' => 'asc',
                    'unit' => 'km',
                ]]);
            }
        }

        return $finalQuery;
    }

    /**
     * The counts of the agenda filters, in one query: how many events each date window, type page and venue lists.
     * Each count keeps the filters its link keeps (AgendaUrlGenerator): a date window those of the page (venue, type,
     * category, keywords), a type the dates, the venue, the category and the keywords, a venue the dates, the type,
     * the category and the keywords. The busiest categories of each type are counted with the filters of the type
     * but the category, which their links replace.
     *
     * @param array<string, DateRange> $dates      the windows to count, by name
     * @param int                      $places     how many venues, the busiest first
     * @param int                      $categories how many categories of each type, the busiest first; none for 0
     */
    public function getFacets(SearchEvent $search, array $dates, int $places, int $categories = 0): AgendaFacets
    {
        $adapter = $this->finder->createRawPaginatorAdapter($this->createFacetsQuery($search, $dates, $places, $categories));

        return AgendaFacets::fromAggregations($adapter->getAggregations());
    }

    /**
     * @param array<string, DateRange> $dates
     */
    public function createFacetsQuery(SearchEvent $search, array $dates, int $places, int $categories = 0): Query
    {
        // The city or the country: a venue page counts the other venues around too. Every count is of events with a
        // session in a window: the ones over before the earliest window, most of the index, are left out before
        // counting (for France, the dates and venues took ~60 ms instead of ~10)
        $from = min([$search->getDateRange()->from, ...array_map(static fn (DateRange $range): DateTimeImmutable => $range->from, array_values($dates))]);
        $filter = new BoolQuery()->addFilter(new Nested()->setPath('sessions')->setQuery($this->createSessionFilter(new DateRange($from, null))));
        $area = $this->createAreaFilter($search);
        if (null !== $area) {
            $filter->addFilter($area);
        }

        $query = Query::create($filter);
        $query->setSize(0);
        $query->setTrackTotalHits(false);

        $window = new Nested()->setPath('sessions')->setQuery($this->createSessionFilter($search->getDateRange()));

        $windows = new Filters('windows');
        foreach ($dates as $name => $range) {
            $windows->addFilter(new Nested()->setPath('sessions')->setQuery($this->createSessionFilter($range)), $name);
        }

        // "all": the agenda without a type. The types found last night, not each type's full-text search: running the
        // five of them took ~0.5 s on every agenda page, whatever its city (the fuzzy terms expand over the whole
        // index). An event imported today counts on its type links from the next night; the type page lists it now.
        $types = new Filters('types');
        $types->addFilter(new MatchAll(), 'all');
        foreach (AgendaType::cases() as $type) {
            $types->addFilter(new Term(['agendaTypes' => $type->value]), $type->value);
        }

        $venue = $this->createPlaceFilter($search);
        $type = $this->createTypeFilter($search->getType());
        $keywords = $this->createTextQuery($search);
        $category = $this->createTagFilter($search);

        if ([] !== $dates) {
            $query->addAggregation($this->createFilterAggregation('dates', [$venue, $type, $keywords, $category], $windows));
        }

        $query->addAggregation($this->createFilterAggregation('types', [$window, $venue, $keywords, $category], $types));
        $query->addAggregation($this->createFilterAggregation('places', [$window, $type, $keywords, $category], new TermsAggregation('ids')->setField('place.id')->setSize($places)));

        if ($categories > 0) {
            $typeCategories = new Filters('types');
            foreach (AgendaType::cases() as $agendaType) {
                $typeCategories->addFilter(new Term(['agendaTypes' => $agendaType->value]), $agendaType->value);
            }

            $typeCategories->addAggregation(new TermsAggregation('categories')->setField('category.id')->setSize($categories));
            $query->addAggregation($this->createFilterAggregation('typeCategories', [$window, $venue, $keywords], $typeCategories));
        }

        return $query;
    }

    /**
     * The ids of the events a type page lists from a day on, whatever their place: what
     * app:events:classify-agenda-types stores as their agenda types.
     */
    public function createAgendaTypeQuery(AgendaType $type, DateTimeImmutable $from): Query
    {
        $query = Query::create(new BoolQuery()
            ->addFilter(new Nested()->setPath('sessions')->setQuery($this->createSessionFilter(new DateRange($from, null))))
            ->addFilter($this->createTypeQuery($type)));
        $query->setSource(false);
        $query->setSort(['_doc']);

        return $query;
    }

    /**
     * @param list<AbstractQuery|null> $filters the filters the counts apply, null for none
     */
    private function createFilterAggregation(string $name, array $filters, AbstractAggregation $counts): Filter
    {
        $filters = array_filter($filters);
        $filter = new BoolQuery();
        foreach ($filters as $clause) {
            $filter->addFilter($clause);
        }

        // An empty bool query is sent as "bool": [], which Elasticsearch rejects
        return new Filter($name, [] === $filters ? new MatchAll() : $filter)->addAggregation($counts);
    }

    /**
     * The venues the visitor chose (a venue page), which replace the location.
     */
    private function createPlaceFilter(SearchEvent $search): ?AbstractQuery
    {
        return [] !== $search->getLieux() ? new Terms('place.id', $search->getLieux()) : null;
    }

    /**
     * The country, or the city and the cities around it.
     */
    private function createAreaFilter(SearchEvent $search): ?AbstractQuery
    {
        $location = $search->getLocation();
        if (null !== $location && $location->isCountry()) {
            return new Term(['place.country.id' => mb_strtolower((string) $location->getCountry()->getId())]);
        }

        if (null !== $location && $location->isCity()) {
            $city = $location->getCity();

            return new BoolQuery()
                ->addShould(new GeoDistance('place.city.location', $city->getLocation(), $search->getRange() . 'km'))
                ->addShould(new Term(['place.city.id' => $city->getId()]));
        }

        return null;
    }

    /**
     * The sessions overlapping a window, a query on the nested sessions: still running on its first day, and
     * started by its last one.
     */
    private function createSessionFilter(DateRange $range): BoolQuery
    {
        $sessionFilter = new BoolQuery();
        $sessionFilter->addFilter(new Range('sessions.endAt', [
            'gte' => $range->from->format('Y-m-d'),
        ]));

        if (null !== $range->to) {
            $sessionFilter->addFilter(new Range('sessions.startAt', [
                'lte' => $range->to->format('Y-m-d'),
            ]));
        }

        return $sessionFilter;
    }

    private function createTextQuery(SearchEvent $search): ?AbstractQuery
    {
        if (!$search->getTerm()) {
            return null;
        }

        return $this->createKeywordsQuery($search->getTerm());
    }

    /**
     * Events naming all these words, fuzzily, in one of their fields.
     */
    private function createKeywordsQuery(string $keywords): MultiMatch
    {
        return new MultiMatch()
            ->setFields([
                'name^5',
                'name.heavy^5',
                'placeName^3',
                'place.name^3',
                'placeCity^2',
                'place.cityName^2',
                'place.cityPostalCode^3',
                'placePostalCode',
                'placeStreet',
                'place.street',
                'description',
                'description.heavy',
                'type',
                'category.name',
                'themes.name',
            ])
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($keywords)
        ;
    }

    private function createTypeFilter(?AgendaType $type): ?AbstractQuery
    {
        return null !== $type ? $this->createTypeQuery($type) : null;
    }

    /**
     * The events of a type page: its synonyms all together, as typed keywords are, found almost nothing ("soirée,
     * étudiant, bar, discothèque, boîte de nuit, after work" never is), so an event naming any one of them is listed
     * too.
     */
    private function createTypeQuery(AgendaType $type): BoolQuery
    {
        return $this->createAnyTermQuery($type->getTerms())
            ->addShould($this->createKeywordsQuery(implode(' ', $type->getTerms())));
    }

    private function createTagFilter(SearchEvent $search): ?AbstractQuery
    {
        // Filter by tag ID (new Tag entity)
        if (null !== $search->getTagId()) {
            $tagFilter = new BoolQuery();
            $tagFilter->setMinimumShouldMatch(1);
            // Match category.id
            $tagFilter->addShould(new Term(['category.id' => $search->getTagId()]));
            // Match themes.id (nested)
            $nestedQuery = new Nested();
            $nestedQuery->setPath('themes');
            $nestedQuery->setQuery(new Term(['themes.id' => $search->getTagId()]));
            $tagFilter->addShould($nestedQuery);

            return $tagFilter;
        }

        if ($search->getTag()) {
            // Legacy: filter by tag string (deprecated)
            $query = new MultiMatch();
            $query
                ->setQuery($search->getTag())
                ->setFields(['type', 'category.name', 'themes.name']);

            return $query;
        }

        return null;
    }

    /**
     * Events naming any of these terms, each as a phrase, in their name, category, type or
     * one of their themes.
     *
     * @param list<string> $terms
     */
    private function createAnyTermQuery(array $terms): BoolQuery
    {
        $query = new BoolQuery();
        $query->setMinimumShouldMatch(1);
        foreach ($terms as $term) {
            $query->addShould(new MultiMatch()
                ->setQuery($term)
                ->setType(MultiMatch::TYPE_PHRASE)
                ->setFields(['name^5', 'name.heavy^5', 'category.name^3', 'type']));
            // Themes are nested documents, which a query on the event itself never reaches
            $query->addShould(new Nested()->setPath('themes')->setQuery(new MatchPhrase('themes.name', $term)));
        }

        return $query;
    }

    /**
     * Returns a paginated list of hybrid results with highlights.
     *
     * @return PagerfantaInterface<HybridResult>
     */
    public function findWithHighlightsPaginated(string $query): PagerfantaInterface
    {
        $multiMatch = new MultiMatch();
        $multiMatch
            ->setFields([
                'name^5',
                'name.heavy^5',
                'placeName^3',
                'place.name^3',
                'placeCity^2',
                'place.cityName^2',
                'description',
            ])
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($query);

        $finalQuery = Query::create($multiMatch);

        // Add highlighting
        $finalQuery->setHighlight([
            'fields' => [
                'name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
                'place.name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
            ],
        ]);

        $adapter = $this->createHybridPaginatorAdapter($finalQuery);

        return new Pagerfanta(new FantaPaginatorAdapter($adapter));
    }
}
