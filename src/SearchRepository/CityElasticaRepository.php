<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Elastica\Query;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\PagerfantaInterface;

final class CityElasticaRepository extends Repository
{
    public function findWithSearch(?string $q): PagerfantaInterface
    {
        $query = new MultiMatch();
        $query
            ->setFields([
                'postal_codes^10',
                'country.name^5',
                'name^3',
                'parent.name',
            ])
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($q ?? '')
        ;

        $finalQuery = Query::create($query);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        return $this->findPaginated($finalQuery);
    }

    /**
     * @return list<HybridResult>
     */
    public function findWithHighlights(string $query, int $limit = 5): array
    {
        $multiMatch = new MultiMatch();
        $multiMatch
            ->setFields([
                'postal_codes^10',
                'country.name^5',
                'name^3',
                'parent.name',
            ])
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($query);

        $finalQuery = Query::create($multiMatch);
        $finalQuery->setSize($limit);
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        // Add highlighting
        $finalQuery->setHighlight([
            'fields' => [
                'name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
                'country.name' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
            ],
        ]);

        return $this->findHybrid($finalQuery, $limit);
    }
}
