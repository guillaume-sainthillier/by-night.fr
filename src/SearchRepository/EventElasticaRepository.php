<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Entity\City;
use App\Enum\AgendaType;
use App\Enum\DateRangePreset;
use App\Enum\PricePreset;
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
use Elastica\Query\DisMax;
use Elastica\Query\Exists;
use Elastica\Query\FunctionScore;
use Elastica\Query\GeoDistance;
use Elastica\Query\MatchAll;
use Elastica\Query\MatchPhrase;
use Elastica\Query\MatchQuery;
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
            $mainQuery->addMust($this->favourNear($textQuery, $search->getNear()));
        }

        $tagFilter = $this->createTagFilter($search);
        if (null !== $tagFilter) {
            $mainQuery->addFilter($tagFilter);
        }

        $priceFilter = $this->createPriceFilter($search->getPrice());
        if (null !== $priceFilter) {
            $mainQuery->addFilter($priceFilter);
        }

        // Construction de la requête finale
        $finalQuery = Query::create($mainQuery);
        // The hits are loaded from the database by their _id (FOSElastica's transformer): none of the document is read
        $finalQuery->setSource(false);
        // The count stops at 10 000 otherwise ("sur 10 000" on /france). The pages still stop at the result
        // window (ResultWindow); counting the rest took no measurable time, the nested sort already visits every hit
        $finalQuery->setTrackTotalHits(true);
        if (null !== $textQuery) {
            // By relevance, the next date between equals: the ten "Marché de Noël" by date, not in index order
            $finalQuery->addSort(['_score' => ['order' => 'desc']]);
        }

        // Soonest-ending session in the window first: a one-day session sorts by
        // its day, an exhibition still running by its last day, as the range did.
        $finalQuery->addSort(['sessions.endAt' => [
            'order' => 'asc',
            'mode' => 'min',
            'nested' => ['path' => 'sessions', 'filter' => $sessionFilter->toArray()],
        ]]);

        if (null === $textQuery) {
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
     * the category and the keywords, a price shortcut the dates, the venue, the type, the category and the keywords.
     * Every count but the price ones keeps the price shortcut of the page. The busiest categories of each type are
     * counted with the filters of the type but the category, which their links replace.
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

        // "all": the agenda without a type
        $types = new Filters('types');
        $types->addFilter(new MatchAll(), 'all');
        foreach (AgendaType::cases() as $type) {
            $types->addFilter($this->createTypeFilter($type), $type->value);
        }

        $venue = $this->createPlaceFilter($search);
        $type = $this->createTypeFilter($search->getType());
        $keywords = $this->createTextQuery($search);
        $category = $this->createTagFilter($search);
        $price = $this->createPriceFilter($search->getPrice());

        // "any": every price, the unknown ones included
        $prices = new Filters('prices');
        $prices->addFilter(new MatchAll(), 'any');
        foreach (PricePreset::cases() as $preset) {
            $prices->addFilter($this->createPriceQuery($preset), $preset->value);
        }

        if ([] !== $dates) {
            $query->addAggregation($this->createFilterAggregation('dates', [$venue, $type, $keywords, $category, $price], $windows));
        }

        $query->addAggregation($this->createFilterAggregation('types', [$window, $venue, $keywords, $category, $price], $types));
        $query->addAggregation($this->createFilterAggregation('places', [$window, $type, $keywords, $category, $price], new TermsAggregation('ids')->setField('place.id')->setSize($places)));
        $query->addAggregation($this->createFilterAggregation('prices', [$window, $venue, $type, $keywords, $category], $prices));

        if ($categories > 0) {
            $typeCategories = new Filters('types');
            foreach (AgendaType::cases() as $agendaType) {
                $typeCategories->addFilter($this->createTypeFilter($agendaType), $agendaType->value);
            }

            $typeCategories->addAggregation(new TermsAggregation('categories')->setField('category.id')->setSize($categories));
            $query->addAggregation($this->createFilterAggregation('typeCategories', [$window, $venue, $keywords, $price], $typeCategories));
        }

        return $query;
    }

    /**
     * The events with a session from a day on, whatever their place, each hit naming the type pages that list it in its
     * "matched_queries": what app:events:classify-agenda-types stores as their agenda types, in one search for the five
     * types. The type queries are optional clauses (the filter makes them so): they select nothing, and only name the
     * hits. Sorted in index order, the cheapest for a scroll.
     */
    public function createAgendaTypesQuery(DateTimeImmutable $from): Query
    {
        $bool = new BoolQuery()->addFilter(new Nested()->setPath('sessions')->setQuery($this->createSessionFilter(new DateRange($from, null))));
        foreach (AgendaType::cases() as $type) {
            $bool->addShould($this->createTypeQuery($type)->setParam('_name', $type->value));
        }

        $query = Query::create($bool);
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

    private function createPriceFilter(?PricePreset $price): ?Range
    {
        return null !== $price ? $this->createPriceQuery($price) : null;
    }

    /**
     * The events whose lowest price to get in is at most the shortcut's: an event without a known price is left out.
     */
    private function createPriceQuery(PricePreset $price): Range
    {
        return new Range('startingPrice', ['lte' => $price->getMaxPrice()]);
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
            // A keyword holding the code as stored ("FR")
            return new Term(['place.country.id' => (string) $location->getCountry()->getId()]);
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
     * The events of a city and around it scored up to three times as high: ×3 within 10 km, ×2 at 40 km, about ×1 past
     * 100 km. A visitor of Toulouse searching "marché de noël" gets the markets around Toulouse first, the rest of
     * France after. An event whose place has no city (13% of those to come) is not around: Elasticsearch scores a
     * missing location as the closest, which put them all first.
     */
    private function favourNear(AbstractQuery $query, ?City $city): AbstractQuery
    {
        if (null === $city?->getLatitude() || null === $city->getLongitude()) {
            return $query;
        }

        return new FunctionScore()
            ->setQuery($query)
            ->addWeightFunction(1.0)
            ->addDecayFunction(FunctionScore::DECAY_GAUSS, 'place.city.location', \sprintf('%F,%F', $city->getLatitude(), $city->getLongitude()), '30km', '10km', 0.5, 2.0, new Exists('place.city.location'))
            ->setScoreMode(FunctionScore::SCORE_MODE_SUM)
            ->setBoostMode(FunctionScore::BOOST_MODE_MULTIPLY);
    }

    /**
     * The synonyms of a type page, for its nightly classification (createTypeQuery()): events naming all these words,
     * fuzzily, in one of their fields or in the name of one of their themes. The best of the two counts, as the best
     * field of the multi_match does: a theme is one more field. Kept apart from the keywords a visitor types
     * (createKeywordsQuery()), so that a change of the search leaves the type pages as they are.
     */
    private function createTypeKeywordsQuery(string $keywords): DisMax
    {
        return new DisMax()
            ->addQuery(new MultiMatch()
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
                ])
                ->setFuzziness(Fuzzy::FUZZINESS)
                ->setPrefixLength(Fuzzy::PREFIX_LENGTH)
                ->setOperator('AND')
                ->setQuery($keywords))
            // Themes are nested documents, which a query on the event itself never reaches
            ->addQuery(new Nested()
                ->setPath('themes')
                ->setScoreMode('max')
                ->setQuery(new MatchQuery()
                    ->setFieldQuery('themes.name', $keywords)
                    ->setFieldFuzziness('themes.name', Fuzzy::FUZZINESS)
                    ->setFieldPrefixLength('themes.name', Fuzzy::PREFIX_LENGTH)
                    ->setFieldOperator('themes.name', MatchQuery::OPERATOR_AND)));
    }

    /**
     * The events holding every word searched, the stop words aside ("marché de noël" needs "marché" and "noël"), scored
     * by the best way they hold them:
     * - as typed, each in any of the fields that name the event (its name, its venue, its city, its category, its
     *   type, its postal code): "concert toulouse" finds the event named "Concert" in Toulouse
     * - inflected ("spectacles enfants"), in its name or its city. Not its venue: "salsa" found the venue named "Ferme
     *   De Salsas"
     * - in the name of one of its themes
     * - in its description, far less
     * - with a typo, in its name only and far less still: never above an event naming the words as typed. In any
     *   field, a rare look-alike outscored the word searched ("toulouse" found the Zénith of Toulon first, "salsa" a
     *   venue named Salsas, "bikini" an event named "Bibbidi Doo").
     *
     * @param bool $asTyped whether the last word may be unfinished (the header search, as the visitor types)
     */
    private function createKeywordsQuery(string $keywords, bool $asTyped = false): DisMax
    {
        $query = new DisMax()
            ->setTieBreaker(0.3)
            ->addQuery(new MultiMatch()
                ->setType(MultiMatch::TYPE_CROSS_FIELDS)
                ->setQuery($keywords)
                ->setFields([
                    'name^5',
                    'placeName^3',
                    'place.name^3',
                    'placeCity^2',
                    'place.cityName^2',
                    'place.cityPostalCode^2',
                    'placePostalCode',
                    'placeStreet',
                    'place.street',
                    'category.name',
                    'type',
                ])
                // The fields read their words alike but under several names: one analyzer makes them one group, each
                // word searched across all of them
                ->setAnalyzer('french_search')
                ->setOperator(MultiMatch::OPERATOR_AND)
                ->setParam('boost', 3))
            ->addQuery(new MultiMatch()
                ->setType(MultiMatch::TYPE_CROSS_FIELDS)
                ->setQuery($keywords)
                ->setFields(['name.heavy^5', 'place.cityName.heavy^2'])
                ->setAnalyzer('french_heavy_search')
                ->setOperator(MultiMatch::OPERATOR_AND)
                ->setParam('boost', 2.5))
            // Themes are nested documents, which a query on the event itself never reaches
            ->addQuery(new Nested()
                ->setPath('themes')
                ->setScoreMode('max')
                ->setQuery(new MatchQuery()
                    ->setFieldQuery('themes.name', $keywords)
                    ->setFieldOperator('themes.name', MatchQuery::OPERATOR_AND))
                ->setParam('boost', 2))
            ->addQuery(new MultiMatch()
                ->setQuery($keywords)
                ->setFields(['description.heavy'])
                ->setOperator(MultiMatch::OPERATOR_AND)
                ->setParam('boost', 0.5))
            ->addQuery(new MultiMatch()
                ->setQuery($keywords)
                ->setFields(['name'])
                ->setFuzziness(Fuzzy::FUZZINESS)
                ->setPrefixLength(Fuzzy::PREFIX_LENGTH)
                ->setOperator(MultiMatch::OPERATOR_AND)
                ->setParam('boost', 0.1));

        if ($asTyped) {
            // The beginnings of the words (name.autocomplete): "orel" finds Orelsan, "marché de no" the markets of Noël
            $query->addQuery(new MultiMatch()
                ->setType(MultiMatch::TYPE_CROSS_FIELDS)
                ->setQuery($keywords)
                ->setFields(['name.autocomplete^5', 'place.name.autocomplete^3'])
                ->setOperator(MultiMatch::OPERATOR_AND));
        }

        return $query;
    }

    /**
     * The events of a type page, and of its counts: the types stored on the events (Event::$agendaTypes), found by
     * createTypeQuery() each night. Running that full-text search on each page took ~0.1–0.15 s per type, ~0.5 s for
     * the five type links of every agenda page (the fuzzy terms expand over the whole index). An event imported or
     * changed today shows on its type pages once classified again.
     *
     * @return ($type is null ? null : Term)
     */
    private function createTypeFilter(?AgendaType $type): ?Term
    {
        return null !== $type ? new Term(['agendaTypes' => $type->value]) : null;
    }

    /**
     * The events a type page lists, by full text: its synonyms all together, as typed keywords are, found almost
     * nothing ("soirée, étudiant, bar, discothèque, boîte de nuit, after work" never is), so an event naming any one
     * of them is listed too.
     */
    private function createTypeQuery(AgendaType $type): BoolQuery
    {
        return $this->createAnyTermQuery($type->getTerms())
            ->addShould($this->createTypeKeywordsQuery(implode(' ', $type->getTerms())));
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
     * The events of the header search, with their highlights, as the visitor types: the keywords of the search page,
     * the last word possibly unfinished, around the city of the page first. Only those with a session still to come,
     * as the agenda lists: over the whole index, the best scores were mostly events long over ("marché de noël"
     * suggested the markets of 2016 to 2022), and the search took 4 to 6 times longer.
     *
     * @return PagerfantaInterface<HybridResult>
     */
    public function findWithHighlightsPaginated(string $query, ?City $near = null): PagerfantaInterface
    {
        $sessionFilter = $this->createSessionFilter(DateRangePreset::Anytime->range());
        $finalQuery = Query::create(new BoolQuery()
            ->addMust($this->favourNear($this->createKeywordsQuery($query, asTyped: true), $near))
            ->addFilter(new Nested()->setPath('sessions')->setQuery($sessionFilter)));
        // The next date between equals, as the search page
        $finalQuery->addSort(['_score' => ['order' => 'desc']]);
        $finalQuery->addSort(['sessions.endAt' => [
            'order' => 'asc',
            'mode' => 'min',
            'nested' => ['path' => 'sessions', 'filter' => $sessionFilter->toArray()],
        ]]);
        // Loaded from the database by their _id; the highlights come without the document
        $finalQuery->setSource(false);

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
