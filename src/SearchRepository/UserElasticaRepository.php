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
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;

final class UserElasticaRepository extends Repository
{
    public function findWithSearch(?string $q): AdapterInterface
    {
        $query = new BoolQuery();

        $match = new MultiMatch();
        $match
            ->setFields([
                'username^5',
                'lastname^3',
                'firstname',
            ])
            ->setQuery($q ?? '')
            ->setFuzziness('auto')
            ->setOperator('AND')
        ;

        $query->addFilter($match);

        $finalQuery = Query::create($query);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields

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
                'username^5',
                'lastname^3',
                'firstname',
            ])
            ->setQuery($query)
            ->setFuzziness('auto')
            ->setOperator('AND');

        $finalQuery = Query::create($multiMatch);

        // Add highlighting
        $finalQuery->setHighlight([
            'fields' => [
                'username' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
                'firstname' => [
                    'pre_tags' => ['__aa-highlight__'],
                    'post_tags' => ['__/aa-highlight__'],
                    'number_of_fragments' => 0,
                ],
                'lastname' => [
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
