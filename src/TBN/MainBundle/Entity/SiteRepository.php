<?php

namespace TBN\MainBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * SiteRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SiteRepository extends EntityRepository
{
    public function findRandom(Site $site, $limit = 5) {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.id != :id')
            ->setParameter('id', $site->getId());

        $results = $qb->getQuery()
            ->getResult();

        shuffle($results);
        return array_slice($results, 0, $limit);
    }
}
