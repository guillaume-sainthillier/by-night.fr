<?php

namespace TBN\UserBundle\SearchRepository;

use FOS\ElasticaBundle\Repository;
use TBN\MainBundle\Entity\Site;
use Elastica\Query\Term;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;

class UserRepository extends Repository
{

    /**
     * @param Site $site
     * @param string $q
     * @return \Pagerfanta\Pagerfanta
     */
    public function findWithSearch(Site $site, $q)
    {
        $query = new BoolQuery;
        $query->addFilter(
            new Term(['site.id' => $site->getId()])
        );

        $match = new MultiMatch;
        $match->setQuery($q)
            ->setFields(['username', 'firstname', 'lastname'])
            ->setFuzziness(0.8)
            ->setMinimumShouldMatch('80%');

        $query->addFilter($match);

        //Final Query
        return $this->findPaginated($query);
    }
}
