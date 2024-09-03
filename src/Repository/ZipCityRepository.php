<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ZipCity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZipCity>
 * @method ZipCity|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZipCity|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZipCity[]    findAll()
 * @method ZipCity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ZipCityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZipCity::class);
    }

    /**
     * @param string      $postalCode
     * @param string|null $city
     * @param string      $country
     */
    public function findOneByPostalCodeAndCity($postalCode, $city, $country = null): ?ZipCity
    {
        $cities = $this->findAllByPostalCodeOrCity($postalCode, $city, $country);
        if (1 === \count($cities)) {
            return $cities[0];
        }

        return null;
    }

    /**
     * @return ZipCity[]
     */
    private function findAllByPostalCodeOrCity(?string $postalCode, ?string $city, ?string $countryId): array
    {
        $query = $this
            ->createQueryBuilder('zc');

        if ($countryId) {
            $query->where('zc.country = :country')
                ->setParameter('country', $countryId);
        }

        if ($postalCode) {
            $query
                ->andWhere('zc.postalCode = :postalCode')
                ->setParameter('postalCode', $postalCode);
        }

        if ($city) {
            $cities = [];
            $city = preg_replace("#(^|\s)st\s#i", '$1saint ', $city);
            $city = str_replace('’', "'", (string) $city);
            $cities[] = $city;
            $cities[] = str_replace(' ', '-', $city);
            $cities[] = str_replace('-', ' ', $city);
            $cities[] = str_replace("'", '', $city);
            $cities[] = str_replace('’', "'", $city);
            $cities[] = str_replace("'", '’', $city);
            $cities = array_map('mb_strtolower', $cities);
            $cities = array_unique($cities);

            $query
                ->andWhere('LOWER(zc.name) IN(:cities)')
                ->setParameter('cities', $cities);
        }

        return $query
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ZipCity[]
     */
    public function findAllByCity(?string $city, ?string $countryId = null): array
    {
        return $this->findAllByPostalCodeOrCity(null, $city, $countryId);
    }

    /**
     * @return ZipCity[]
     */
    public function findAllByPostalCode(?string $postalCode, ?string $countryId = null): array
    {
        return $this->findAllByPostalCodeOrCity($postalCode, null, $countryId);
    }
}
