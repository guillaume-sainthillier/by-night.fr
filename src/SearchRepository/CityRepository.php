<?php

namespace App\SearchRepository;

use Elastica\Query;
use Elastica\Query\Match;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Repository;

class CityRepository extends Repository
{
    /**
     * @param string $q
     *
     * @return PaginatorAdapterInterface
     */
    public function findWithSearch($q)
    {
        $match = new Match('name', $q);
        $query = Query::create($match);

        return $this->createPaginatorAdapter($query);
    }
}
