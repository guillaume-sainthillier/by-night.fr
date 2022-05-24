<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Elastica\Query;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Repository;

class CityElasticaRepository extends Repository
{
    /**
     * @param string $q
     */
    public function findWithSearch(?string $q): PaginatorAdapterInterface
    {
        $query = new MultiMatch();
        $query
            ->setQuery($q ?? '')
        ;

        $finalQuery = Query::create($query);
        $finalQuery->setSource([]); // Grab only id as we don't need other fields
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        return $this->createPaginatorAdapter($finalQuery);
    }
}
