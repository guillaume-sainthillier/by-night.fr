<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Entity\Tag;
use Elastica\Query;
use Elastica\Query\MatchQuery;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;

final class TagElasticaRepository extends Repository
{
    /**
     * @return PagerfantaInterface<Tag>
     */
    public function findWithSearch(string $q): PagerfantaInterface
    {
        $query = new MatchQuery();
        $query->setFieldQuery('name', $q);
        $query->setFieldFuzziness('name', 'auto');

        $finalQuery = Query::create($query);
        $finalQuery->setSource(['id']);
        $finalQuery->addSort(['_score' => 'DESC']);

        return $this->findPaginated($finalQuery);
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
            ])
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($query);

        $finalQuery = Query::create($multiMatch);
        $finalQuery->addSort(['_score' => 'DESC']);

        // Add highlighting
        $finalQuery->setHighlight([
            'fields' => [
                'name' => [
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
