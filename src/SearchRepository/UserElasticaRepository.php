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
use FOS\ElasticaBundle\Repository;
use Pagerfanta\PagerfantaInterface;

final class UserElasticaRepository extends Repository
{
    public function findWithSearch(?string $q): PagerfantaInterface
    {
        $query = new BoolQuery();

        $match = new MultiMatch();
        $match
            ->setQuery($q ?? '')
            ->setFuzziness('auto')
            ->setOperator('AND')
        ;

        $query->addFilter($match);

        $finalQuery = Query::create($query);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields

        return $this->findPaginated($finalQuery);
    }
}
