<?php

namespace AppBundle\SearchRepository;

use Elastica\Query;
use Elastica\Query\Match;
use FOS\ElasticaBundle\Repository;

class CityRepository extends Repository
{
    /**
     * @param string $q
     *
     * @return \Pagerfanta\Pagerfanta
     */
    public function findWithSearch($q)
    {
        $match = new Match('name', $q);

        $query = Query::create($match);
//        $query->addSort(['population' => 'DESC']);

        return $this->createPaginatorAdapter($query);
    }
}
