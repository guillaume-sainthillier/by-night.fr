<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
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

    /**
     * @throws NonUniqueResultException
     */
    public function getFromRegionOrDepartment(?string $region, ?string $department): ?Country
    {
        $qb = $this
            ->createQueryBuilder('c')
            ->leftJoin(AdminZone1::class, 'admin_zone1', 'WITH', 'admin_zone1.country = c')
            ->leftJoin(AdminZone2::class, 'admin_zone2', 'WITH', 'admin_zone2.country = c');

        if ($region) {
            $qb
                ->orWhere('LOWER(admin_zone1.name) LIKE :region')
                ->setParameter('region', '%' . mb_strtolower($region) . '%');
        }

        if ($department) {
            $qb
                ->orWhere('LOWER(admin_zone2.name) LIKE :department')
                ->setParameter('department', '%' . mb_strtolower($department) . '%');
        }

        return $qb
            ->groupBy('c')
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    public function findByName($country)
    {
        return $this
            ->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :country OR LOWER(c.displayName) = :country OR c.id = :country')
            ->setParameter('country', mb_strtolower($country))
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }
}
