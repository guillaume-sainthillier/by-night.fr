<?php

namespace App\SearchRepository;

use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Repository;

class UserRepository extends Repository
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
