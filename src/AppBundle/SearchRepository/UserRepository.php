<?php

namespace AppBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use AppBundle\Entity\Site;
use Elastica\Query\Term;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;

class UserRepository extends Repository
{

    /**
     * @param string $q
     * @return \Pagerfanta\Pagerfanta
     */
    public function findWithSearch($q)
    {
        $query = new BoolQuery;

        $match = new MultiMatch;
        $match->setQuery($q)
            ->setFields(['username', 'firstname', 'lastname'])
            ->setFuzziness(0.8)
            ->setMinimumShouldMatch('80%');

        $query->addFilter($match);

        //Final Query
        return $this->createPaginatorAdapter($query);
    }
}
