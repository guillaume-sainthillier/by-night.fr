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
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\PagerfantaInterface;

class EventElasticaRepository extends Repository
{
    /**
     * @var string
     */
    final public const EXPO_TERMS = 'exposition, salon';

    /**
     * @var string
     */
    final public const CONCERT_TERMS = 'concert, musique, artiste';

    /**
     * @var string
     */
    final public const FAMILY_TERMS = 'famille, enfants';

    /**
     * @var string
     */
    final public const SHOW_TERMS = 'spectacle, exposition, théâtre, comédie';

    /**
     * @var string
     */
    final public const STUDENT_TERMS = 'soirée, étudiant, bar, discothèque, boîte de nuit, after work';

    public function findWithSearch(SearchEvent $search): PagerfantaInterface
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
                $mainQuery->addFilter(new Range('end_date', [
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
                    ->addMust(new Range('start_date', [
                        'lte' => $search->getFrom()->format('Y-m-d'),
                    ])
                    )
                    ->addMust(new Range('end_date', [
                        'gte' => $search->getTo()->format('Y-m-d'),
                    ]));

                // Cas2 : [deb; fin] € [debForm; finForm] -> (deb > debForm AND fin < finForm)
                $cas2 = new BoolQuery();
                $cas2
                    ->addMust(new Range('start_date', [
                        'gte' => $search->getFrom()->format('Y-m-d'),
                    ])
                    )
                    ->addMust(new Range('end_date', [
                        'lte' => $search->getTo()->format('Y-m-d'),
                    ]));

                // Cas3 : deb € [debForm; finForm] -> (deb > debForm AND deb < finForm)
                $cas3 = new Range('start_date', [
                    'gte' => $search->getFrom()->format('Y-m-d'),
                    'lte' => $search->getTo()->format('Y-m-d'),
                ]);

                // Cas4 : fin € [debForm; finForm] -> (fin > debForm AND fin < finForm)
                $cas4 = new Range('end_date', [
                    'gte' => $search->getFrom()->format('Y-m-d'),
                    'lte' => $search->getTo()->format('Y-m-d'),
                ]);

                $filterDate
                    ->addShould($cas1)
                    ->addShould($cas2)
                    ->addShould($cas3)
                    ->addShould($cas4)
                ;

                $mainQuery->addFilter($filterDate);
            }
        }

        // Query
        if ($search->getTerm()) {
            $sortByScore = true;
            $query = new MultiMatch();
            $query
                ->setFuzziness('auto')
                ->setOperator('AND')
                ->setQuery($search->getTerm())
            ;
            $mainQuery->addMust($query);
        }

        if ($search->getTag()) {
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
            $finalQuery->addSort(['end_date' => 'asc']);

            if ($location) {
                $finalQuery->addSort(['_geo_distance' => [
                    'place.city.location' => $location,
                    'order' => 'asc',
                    'unit' => 'km',
                ]]);
            }
        }

        return $this->findPaginated($finalQuery);
    }
}
