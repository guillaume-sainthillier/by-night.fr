<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ZipCity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ZipCity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZipCity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZipCity[]    findAll()
 * @method ZipCity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZipCityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZipCity::class);
    }

    /**
     * @param string      $postalCode
     * @param string|null $city
     * @param string      $country
     *
     * @return ZipCity|null
     */
    public function findByPostalCodeAndCity($postalCode, $city, $country = null)
    {
        $cities = $this->findByPostalCodeOrCity($postalCode, $city, $country);
        if (1 === \count($cities)) {
            return $cities[0];
        }

        return null;
    }

    /**
     * @param string|null $postalCode
     * @param string|null $city
     * @param string      $country
     *
     * @return ZipCity[]
     */
    private function findByPostalCodeOrCity($postalCode = null, $city = null, $country = null)
    {
        $query = $this
            ->createQueryBuilder('zc');

        if ($country) {
            $query->where('zc.country = :country')
                ->setParameter('country', $country);
        }

        if ($postalCode) {
            $query
                ->andWhere('zc.postalCode = :postalCode')
                ->setParameter('postalCode', $postalCode);
        }

        if ($city) {
            $cities = [];
            $city = \preg_replace("#(^|\s)st\s#i", '$1saint ', $city);
            $city = \str_replace('’', "'", $city);
            $cities[] = $city;
            $cities[] = \str_replace(' ', '-', $city);
            $cities[] = \str_replace('-', ' ', $city);
            $cities[] = \str_replace("'", '', $city);
            $cities[] = \str_replace('’', '\'', $city);
            $cities[] = \str_replace('\'', '’', $city);
            $cities = array_map('mb_strtolower', $cities);
            $cities = \array_unique($cities);

            $query
                ->andWhere('LOWER(zc.name) IN(:cities)')
                ->setParameter('cities', $cities);
        }

        return $query
            ->getQuery()
            ->useQueryCache(true)
            ->enableResultCache()
            ->getResult();
    }

    /**
     * @param string|null $city
     * @param string      $country
     *
     * @return ZipCity[]
     */
    public function findByCity($city, $country = null)
    {
        return $this->findByPostalCodeOrCity(null, $city, $country);
    }

    /**
     * @param string $postalCode
     * @param string $country
     *
     * @return ZipCity[]
     */
    public function findByPostalCode($postalCode, $country = null)
    {
        return $this->findByPostalCodeOrCity($postalCode, null, $country);
    }
}
