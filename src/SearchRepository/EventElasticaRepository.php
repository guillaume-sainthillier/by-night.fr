<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Search\SearchEvent;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\GeoDistance;
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
    public const string EXPO_TERMS = 'exposition, salon';

    public const string CONCERT_TERMS = 'concert, musique, artiste';

    public const string FAMILY_TERMS = 'famille, enfants';

    public const string SHOW_TERMS = 'spectacle, exposition, théâtre, comédie';

    public const string STUDENT_TERMS = 'soirée, étudiant, bar, discothèque, boîte de nuit, after work';

    public function findWithSearch(SearchEvent $search): AdapterInterface
    {
        return new FantaPaginatorAdapter($this->createPaginatorAdapter($this->createSearchQuery($search)));
    }

    /**
     * The agenda query: location, then sessions (one must overlap the requested
     * window), then text, tags and types. Sorted by the soonest-ending session in the
     * window, so an event is listed by its next date rather than by its overall range,
     * or by relevance when a term is searched.
     */
    public function createSearchQuery(SearchEvent $search): Query
    {
        $sortByScore = false;
        $mainQuery = new BoolQuery();
        $location = null;
        if ([] !== $search->getLieux()) {
            $mainQuery->addFilter(
                new Terms('place.id', $search->getLieux())
            );
        } elseif ($search->getLocation() && $search->getLocation()->isCountry()) {
            $mainQuery->addFilter(
                new Term(['place.country.id' => mb_strtolower((string) $search->getLocation()->getCountry()->getId())])
            );
        } elseif ($search->getLocation() && $search->getLocation()->isCity()) {
            $location = $search->getLocation()->getCity()->getLocation();
            $filterBool = new BoolQuery();
            $filterBool
                ->addShould(new GeoDistance('place.city.location', $search->getLocation()->getCity()->getLocation(), $search->getRange() . 'km'))
                ->addShould(new Term(['place.city.id' => $search->getLocation()->getCity()->getId()]))
            ;

            $mainQuery->addFilter($filterBool);
        }

        // One entry per session in the index: the event is listed when one of its
        // sessions overlaps the requested window, not when its overall range does.
        // The same condition drives the sort below.
        $sessionFilter = null;
        if (null !== $search->getFrom()) {
            $sessionFilter = new BoolQuery();
            $sessionFilter->addFilter(new Range('sessions.endAt', [
                'gte' => $search->getFrom()->format('Y-m-d'),
            ]));

            if (null !== $search->getTo()) {
                $sessionFilter->addFilter(new Range('sessions.startAt', [
                    'lte' => $search->getTo()->format('Y-m-d'),
                ]));
            }

            $mainQuery->addFilter(new Nested()->setPath('sessions')->setQuery($sessionFilter));
        }

        // Query
        if ($search->getTerm()) {
            $sortByScore = true;
            $query = new MultiMatch();
            $query
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
                ->setQuery($search->getTerm())
            ;

            $typeTerms = $search->getTypeTerms();
            if ([] !== $typeTerms) {
                // A type page: its synonyms all together, as typed keywords are, found almost
                // nothing ("soirée, étudiant, bar, discothèque, boîte de nuit, after work" never
                // is), so an event naming any one of them is listed too
                $anyTypeTerm = $this->createAnyTermQuery($typeTerms);
                $anyTypeTerm->addShould($query);
                $query = $anyTypeTerm;
            }

            $mainQuery->addMust($query);
        }

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
            $mainQuery->addFilter($tagFilter);
        } elseif ($search->getTag()) {
            // Legacy: filter by tag string (deprecated)
            $query = new MultiMatch();
            $query
                ->setQuery($search->getTag())
                ->setFields(['type', 'category.name', 'themes.name']);
            $mainQuery->addFilter($query);
        }

        if ([] !== $search->getType()) {
            $query = new MultiMatch();
            $query
                ->setQuery(implode(' ', $search->getType()))
                ->setFields(['type', 'category.name']);

            // Themes are nested documents, which a query on the event itself never reaches
            $typeFilter = new BoolQuery();
            $typeFilter->setMinimumShouldMatch(1);
            $typeFilter->addShould($query);
            foreach ($search->getType() as $type) {
                $typeFilter->addShould(new Nested()->setPath('themes')->setQuery(new MatchPhrase('themes.name', $type)));
            }

            $mainQuery->addFilter($typeFilter);
        }

        // Construction de la requête finale
        $finalQuery = Query::create($mainQuery);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields
        if (!$sortByScore) {
            // Soonest-ending session in the window first: a one-day session sorts by
            // its day, an exhibition still running by its last day, as the range did.
            $sort = ['order' => 'asc', 'mode' => 'min', 'nested' => ['path' => 'sessions']];
            if (null !== $sessionFilter) {
                $sort['nested']['filter'] = $sessionFilter->toArray();
            }

            $finalQuery->addSort(['sessions.endAt' => $sort]);

            if ($location) {
                $finalQuery->addSort(['_geo_distance' => [
                    'place.city.location' => $location,
                    'order' => 'asc',
                    'unit' => 'km',
                ]]);
            }
        }

        return $finalQuery;
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
