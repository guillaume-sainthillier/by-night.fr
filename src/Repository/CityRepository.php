<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\City;
use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method City|null find($id, $lockMode = null, $lockVersion = null)
 * @method City|null findOneBy(array $criteria, array $orderBy = null)
 * @method City[]    findAll()
 * @method City[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
    {
        return parent::createQueryBuilder($alias, $indexBy)
            ->addSelect('p')
            ->addSelect('country')
            ->leftJoin($alias . '.parent', 'p')
            ->join($alias . '.country', 'country');
    }

    public function findAllSitemap(): iterable
    {
        return parent::createQueryBuilder('c')
            ->select('c.slug')
            ->join('App:Place', 'p', 'WITH', 'p.city = c')
            ->join('App:Event', 'a', 'WITH', 'a.place = p')
            ->groupBy('c.slug')
            ->getQuery()
            ->toIterable();
    }

    public function findAllSitemapTags(): iterable
    {
        return parent::createQueryBuilder('c')
            ->select('c.slug, e.typeManifestation, e.categorieManifestation, e.themeManifestation')
            ->join('App:Place', 'p', 'WITH', 'p.city = c')
            ->join('App:Event', 'e', 'WITH', 'e.place = p')
            ->where('e.dateFin >= :from')
            ->setParameter('from', date('Y-m-d'))
            ->groupBy('c.slug, e.typeManifestation, e.categorieManifestation, e.themeManifestation')
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return string[]
     */
    public function findAllRandomNames(Country $country = null, $limit = 5): array
    {
        $qb = parent::createQueryBuilder('c')
            ->select('c.name, c.slug, c2.name AS country')
            ->join('c.country', 'c2');

        if (null !== $country) {
            $qb
                ->where('c2 = :country')
                ->setParameter('country', $country->getId());
        }

        $results = $qb
            ->orderBy('c.population', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getScalarResult();

        shuffle($results);

        return \array_slice($results, 0, $limit);
    }

    public function findAllByName(?string $city, ?string $country = null): array
    {
        $cities = [];
        $city = preg_replace("#(^|\s)st\s#i", '$1saint ', $city);
        $city = str_replace('’', "'", $city);
        $cities[] = $city;
        $cities[] = str_replace(' ', '-', $city);
        $cities[] = str_replace('-', ' ', $city);
        $cities[] = str_replace("'", '', $city);
        $cities = array_unique($cities);

        $qb = parent::createQueryBuilder('c')
            ->where('c.name IN (:cities)')
            ->setParameter('cities', $cities);

        if ($country) {
            $qb
                ->andWhere('c.country = :country')
                ->setParameter('country', $country);
        }

        return $qb
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->enableResultCache()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * @param array<array<string, string>> $namesAndCountries
     *
     * @return City[]
     */
    public function findAllByNamesAndCountries(array $namesAndCountries): array
    {
        if (0 === \count($namesAndCountries)) {
            return [];
        }

        $qb = parent::createQueryBuilder('c');

        $wheres = [];
        foreach (array_values($namesAndCountries) as $i => list($name, $country)) {
            $cities = [];
            $city = preg_replace("#(^|\s)st\s#i", '$1saint ', $name);
            $city = str_replace('’', "'", $city);
            $cities[] = $city;
            $cities[] = str_replace(' ', '-', $city);
            $cities[] = str_replace('-', ' ', $city);
            $cities[] = str_replace("'", '', $city);
            $cities = array_unique($cities);

            $wheres[] = sprintf(
                '(c.name IN (:cities_%d) AND c.country = :country_%d)',
                $i,
                $i
            );
            $qb
                ->setParameter(sprintf('cities_%d', $i), $cities)
                ->setParameter(sprintf('country_%d', $i), $country);
        }

        return $qb
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->enableResultCache()
            ->useQueryCache(true)
            ->getResult();
    }

    /**
     * @param scalar|null $slug
     */
    public function findOneBySlug($slug): ?City
    {
        return parent::createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }
}
