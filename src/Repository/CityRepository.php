<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\CityDto;
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
class CityRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
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

    /**
     * {@inheritDoc}
     */
    public function findAllByDtos(array $dtos): array
    {
        $wheres = [];
        $groupedWheres = [];

        foreach ($dtos as $dto) {
            \assert($dto instanceof CityDto);

            if (null === $dto->name || null === $dto->country || null === $dto->country->entityId) {
                continue;
            }

            $cities = [];
            $city = preg_replace("#(^|\s)st\s#i", '$1saint ', $dto->name);
            $city = str_replace('’', "'", $city);
            $cities[] = $city;
            $cities[] = str_replace(' ', '-', $city);
            $cities[] = str_replace('-', ' ', $city);
            $cities[] = str_replace("'", '', $city);
            $cities = array_unique($cities);

            foreach ($cities as $city) {
                $groupedWheres[$dto->country->entityId][$city] = true;
            }
        }

        if (0 === \count($groupedWheres)) {
            return [];
        }

        $queryBuilder = parent::createQueryBuilder('c')
            ->addSelect('country')
            ->join('c.country', 'country');

        $i = 1;
        foreach ($groupedWheres as $countryId => $cities) {
            $countryPlaceholder = sprintf('country_%d', $i);
            $namesPlaceholder = sprintf('names_%d', $i);
            $wheres[] = sprintf(
                '(c.country = :%s AND c.name IN(:%s))',
                $countryPlaceholder,
                $namesPlaceholder
            );

            $queryBuilder
                ->setParameter($countryPlaceholder, $countryId)
                ->setParameter($namesPlaceholder, array_keys($cities));
            ++$i;
        }

        return $queryBuilder
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->enableResultCache()
            ->useQueryCache(true)
            ->getResult();
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
