<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\CityDto;
use App\Entity\City;
use App\Entity\Country;
use App\Utils\CityManipulator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 * @method City|null find($id, $lockMode = null, $lockVersion = null)
 * @method City|null findOneBy(array $criteria, array $orderBy = null)
 * @method City[]    findAll()
 * @method City[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CityRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, private readonly CityManipulator $cityManipulator)
    {
        parent::__construct($registry, City::class);
    }

    public function createElasticaQueryBuilder(string $alias, $indexBy = null): QueryBuilder
    {
        return $this
            ->createQueryBuilder($alias, $indexBy)
            ->addSelect('zipCities')
            ->leftJoin($alias . '.zipCities', 'zipCities')
        ;
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
        $cityNameWheres = [];
        $postalCodesWheres = [];

        foreach ($dtos as $dto) {
            \assert($dto instanceof CityDto);

            if (
                (null === $dto->name && null === $dto->postalCode)
                || null === $dto->country?->entityId) {
                continue;
            }

            if (null !== $dto->name) {
                $cities = $this->cityManipulator->getCityNameAlternatives($dto->name);
                foreach ($cities as $city) {
                    $cityNameWheres[$dto->country->entityId][$city] = true;
                }
            }

            if (null !== $dto->postalCode) {
                $postalCodesWheres[$dto->country->entityId][$dto->postalCode] = true;
            }
        }

        if ([] === $cityNameWheres && [] === $postalCodesWheres) {
            return [];
        }

        $queryBuilder = parent::createQueryBuilder('c')
            ->addSelect('country')
            ->join('c.country', 'country');

        $i = 1;
        foreach ($cityNameWheres as $countryId => $cityNames) {
            $countryPlaceholder = \sprintf('city_name_country_%d', $i);
            $cityNamesPlaceholder = \sprintf('city_name_names_%d', $i);
            $wheres[] = \sprintf(
                '(c.country = :%s AND c.name IN(:%s))',
                $countryPlaceholder,
                $cityNamesPlaceholder
            );

            $queryBuilder
                ->setParameter($countryPlaceholder, $countryId)
                ->setParameter($cityNamesPlaceholder, array_keys($cityNames));
            ++$i;
        }

        if ([] !== $postalCodesWheres) {
            $queryBuilder->leftJoin('c.zipCities', 'z');
            $i = 1;
            foreach ($postalCodesWheres as $countryId => $postalCodes) {
                $countryPlaceholder = \sprintf('postal_code_country_%d', $i);
                $postalCodesPlaceholder = \sprintf('postal_code_names_%d', $i);
                $wheres[] = \sprintf(
                    '(c.country = :%s AND z.postalCode IN(:%s))',
                    $countryPlaceholder,
                    $postalCodesPlaceholder
                );

                $queryBuilder
                    ->setParameter($countryPlaceholder, $countryId)
                    ->setParameter($postalCodesPlaceholder, array_keys($postalCodes));
                ++$i;
            }
        }

        return $queryBuilder
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return iterable<array>
     */
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

    /**
     * @return iterable<array>
     */
    public function findAllTagsSitemap(): iterable
    {
        return parent::createQueryBuilder('c')
            ->select('c.slug, e.type, e.category, e.theme')
            ->join('App:Place', 'p', 'WITH', 'p.city = c')
            ->join('App:Event', 'e', 'WITH', 'e.place = p')
            ->where('e.endDate >= :from')
            ->setParameter('from', date('Y-m-d'))
            ->groupBy('c.slug, e.type, e.category, e.theme')
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return string[]
     */
    public function findAllRandomNames(?Country $country = null, $limit = 5): array
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
            ->orderBy('c.population', Criteria::DESC)
            ->setMaxResults(50)
            ->getQuery()
            ->getScalarResult();

        shuffle($results);

        return \array_slice($results, 0, $limit);
    }

    public function findAllByName(?string $cityName, ?string $countryId = null): array
    {
        $cities = $this->cityManipulator->getCityNameAlternatives($cityName);

        $qb = parent::createQueryBuilder('c')
            ->where('c.name IN (:cities)')
            ->setParameter('cities', $cities);

        if ($countryId) {
            $qb
                ->andWhere('c.country = :country')
                ->setParameter('country', $countryId);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?City
    {
        return parent::createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
