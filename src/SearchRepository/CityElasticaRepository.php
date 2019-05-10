<?php

namespace App\SearchRepository;

use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Repository;

class CityElasticaRepository extends Repository
{
    /**
     * @param string $q
     *
     * @return PaginatorAdapterInterface
     */
    public function findWithSearch($q)
    {
        $query = new MultiMatch();
        $query
            ->setQuery($q)
            ->setFields([
                'name',
                'parent.name',
                'country.name',
            ]);

        return $this->createPaginatorAdapter($query);
    }
}
