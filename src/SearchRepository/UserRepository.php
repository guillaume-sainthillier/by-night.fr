<?php

namespace AppBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;

class UserRepository extends Repository
{
    /**
     * @param string $q
     *
     * @return \Pagerfanta\Pagerfanta
     */
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
