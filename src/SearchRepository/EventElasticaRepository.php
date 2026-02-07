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

        if (null !== $search->getFrom()) {
            if (null === $search->getTo()) {
                $mainQuery->addFilter(new Range('endDate', [
                    'gte' => $search->getFrom()->format('Y-m-d'),
                ]));
            } else {
                $filterDate = new BoolQuery();
                /*
                 * 4 cas :
                 * 1) [debForm; finForm] € [deb; fin]
                 * 2) [deb; fin] € [debForm; finForm]
                 * 3) deb € [debForm; finForm]
                 * 4) fin € [debForm; finForm]
                */

                // Cas1 : [debForm; finForm] € [deb; fin] -> (deb < debForm AND fin > finForm)
                $cas1 = new BoolQuery();
                $cas1
                    ->addMust(new Range('startDate', [
                        'lte' => $search->getFrom()->format('Y-m-d'),
                    ])
                    )
                    ->addMust(new Range('endDate', [
                        'gte' => $search->getTo()->format('Y-m-d'),
                    ]));

                // Cas2 : [deb; fin] € [debForm; finForm] -> (deb > debForm AND fin < finForm)
                $cas2 = new BoolQuery();
                $cas2
                    ->addMust(new Range('startDate', [
                        'gte' => $search->getFrom()->format('Y-m-d'),
                    ])
                    )
                    ->addMust(new Range('endDate', [
                        'lte' => $search->getTo()->format('Y-m-d'),
                    ]));

                // Cas3 : deb € [debForm; finForm] -> (deb > debForm AND deb < finForm)
                $cas3 = new Range('startDate', [
                    'gte' => $search->getFrom()->format('Y-m-d'),
                    'lte' => $search->getTo()->format('Y-m-d'),
                ]);

                // Cas4 : fin € [debForm; finForm] -> (fin > debForm AND fin < finForm)
                $cas4 = new Range('endDate', [
                    'gte' => $search->getFrom()->format('Y-m-d'),
                    'lte' => $search->getTo()->format('Y-m-d'),
                ]);

                $filterDate
                    ->addShould($cas1)
                    ->addShould($cas2)
                    ->addShould($cas3)
                    ->addShould($cas4)
                    ->setMinimumShouldMatch(1)
                ;

                $mainQuery->addFilter($filterDate);
            }
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
                    'theme',
                    'type',
                    'category.name',
                    'themes.name',
                ])
                ->setFuzziness('auto')
                ->setOperator('AND')
                ->setQuery($search->getTerm())
            ;
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
                ->setFields(['type', 'theme', 'category']);
            $mainQuery->addFilter($query);
        }

        if ([] !== $search->getType()) {
            $query = new MultiMatch();
            $query
                ->setQuery(implode(' ', $search->getType()))
                ->setFields(['type', 'theme', 'category']);
            $mainQuery->addFilter($query);
        }

        // Construction de la requête finale
        $finalQuery = Query::create($mainQuery);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields
        if (!$sortByScore) {
            $finalQuery->addSort(['endDate' => 'asc']);

            if ($location) {
                $finalQuery->addSort(['_geo_distance' => [
                    'place.city.location' => $location,
                    'order' => 'asc',
                    'unit' => 'km',
                ]]);
            }
        }

        return new FantaPaginatorAdapter($this->createPaginatorAdapter($finalQuery));
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
