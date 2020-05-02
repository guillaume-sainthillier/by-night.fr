<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Country|null find($id, $lockMode = null, $lockVersion = null)
 * @method Country|null findOneBy(array $criteria, array $orderBy = null)
 * @method Country[]    findAll()
 * @method Country[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    public function findByName($country)
    {
        return $this
            ->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :country OR LOWER(c.displayName) = :country OR c.id = :country')
            ->setParameter('country', \mb_strtolower($country))
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }
}
