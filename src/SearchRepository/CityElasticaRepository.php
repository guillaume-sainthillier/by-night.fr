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
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Repository;
use Pagerfanta\PagerfantaInterface;

final class CityElasticaRepository extends Repository
{
    public function findWithSearch(?string $q): PagerfantaInterface
    {
        $query = new MultiMatch();
        $query
            ->setFuzziness('auto')
            ->setOperator('AND')
            ->setQuery($q ?? '')
        ;

        $finalQuery = Query::create($query);
        $finalQuery->setSource(['id']); // Grab only id as we don't need other fields
        $finalQuery->addSort(['_score' => 'DESC']);
        $finalQuery->addSort(['population' => 'DESC']);

        return $this->findPaginated($finalQuery);
    }
}
