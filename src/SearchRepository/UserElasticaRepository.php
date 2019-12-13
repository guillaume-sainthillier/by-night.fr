<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Repository;

class UserElasticaRepository extends Repository
{
    public function findWithSearch($q)
    {
        $query = new BoolQuery();

        $match = new MultiMatch();
        $match->setQuery($q)
            ->setFields(['username', 'firstname', 'lastname'])
            ->setMinimumShouldMatch('80%');

        $query->addFilter($match);

        //Final Query
        return $this->createPaginatorAdapter($query);
    }
}
