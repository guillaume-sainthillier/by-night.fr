<?php

namespace TBN\UserBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\MainBundle\Entity\Site;
use Elastica\Filter\Term;
use Elastica\Filter\Bool;
use Elastica\Query;

use Elastica\Query\Filtered;
use Elastica\Query\MultiMatch;

class UserRepository extends Repository {

    /**
     * @param $searchText
     * @return \Pagerfanta\Pagerfanta
     */
    public function findWithSearch(Site $site, $q) {
	
	//Filtres
	$filter = new Bool;
	$filter->addMust(
            new Term(['site.id' => $site->getId()])
        );

	//Query
	$query = new MultiMatch;
	$query->setQuery($q)
		->setFields(['username', 'firstname', 'lastname'])
		->setFuzziness(0.8)
		->setMinimumShouldMatch('80%');

	//Final Query
	$filtered = new Filtered($query, $filter);
	$finalQuery = Query::create($filtered);
        return $this->findPaginated($finalQuery);
    }
}
