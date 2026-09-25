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
use App\Contracts\MultipleEagerLoaderInterface;
use App\Dto\CityDto;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Place;
use App\Utils\CityManipulator;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Override;

/**
 * @extends ServiceEntityRepository<City>
 *
 * @implements DtoFindableRepositoryInterface<CityDto, City>
 * @implements MultipleEagerLoaderInterface<City>
 *
 * @method City|null find($id, $lockMode = null, $lockVersion = null)
 * @method City|null findOneBy(array $criteria, array $orderBy = null)
 * @method City[]    findAll()
 * @method City[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CityRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface, MultipleEagerLoaderInterface
{
    public function __construct(ManagerRegistry $registry, private readonly CityManipulator $cityManipulator)
    {
        parent::__construct($registry, City::class);
    }

    #[Override]
    public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
    {
        return parent::createQueryBuilder($alias, $indexBy)
            ->addSelect('p')
            ->addSelect('country')
            ->leftJoin($alias . '.parent', 'p')
            ->join($alias . '.country', 'country');
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        $entityIds = array_map(static fn (City $entity): ?int => $entity->getId(), $entities);
        if ([] === $entityIds) {
            return;
        }

        $view = $context['view'] ?? null;

        $loadZipCities = fn () => $this
            ->createQueryBuilder('c')
            ->select('PARTIAL c.{id}')
            ->addSelect('z')
            ->leftJoin('c.zipCities', 'z')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $entityIds)
            ->getQuery()
            ->getResult();

        if ('elasticsearch:document' === $view) {
            $loadZipCities();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        $cityNameWheres = [];
        $postalCodesWheres = [];

        foreach ($dtos as $dto) {
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

        // One query per criterion: OR-ed together, the name branch and the joined postal code
        // branch kept MySQL off both indexes and every lookup read all the cities of the country.
        $found = [];
        foreach ([$this->findByNames($cityNameWheres), $this->findByPostalCodes($postalCodesWheres)] as $results) {
            foreach ($results as $city) {
                $found[$city->getId()] = $city;
            }
        }

        return array_values($found);
    }

    /**
     * @param array<string, array<string|int, true>> $cityNameWheres city names by country id
     *
     * @return City[]
     */
    private function findByNames(array $cityNameWheres): array
    {
        if ([] === $cityNameWheres) {
            return [];
        }

        $wheres = [];
        // With its parent: an association to the root of the admin_zone inheritance cannot be
        // proxied, so Doctrine would otherwise load each city's parent with a query of its own
        $queryBuilder = $this->createQueryBuilder('c');

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
                ->setParameter($cityNamesPlaceholder, array_map(strval(...), array_keys($cityNames)), ArrayParameterType::STRING);
            ++$i;
        }

        return $queryBuilder
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, array<string|int, true>> $postalCodesWheres postal codes by country id
     *
     * @return City[]
     */
    private function findByPostalCodes(array $postalCodesWheres): array
    {
        if ([] === $postalCodesWheres) {
            return [];
        }

        $wheres = [];
        // With its parent, see findByNames()
        $queryBuilder = $this
            ->createQueryBuilder('c')
            ->join('c.zipCities', 'z');

        $i = 1;
        foreach ($postalCodesWheres as $countryId => $postalCodes) {
            $countryPlaceholder = \sprintf('postal_code_country_%d', $i);
            $postalCodesPlaceholder = \sprintf('postal_code_names_%d', $i);
            // Filtered on the zip code's own country too, so the lookup starts from the
            // zip_city (country, postal code) index rather than from the cities
            $wheres[] = \sprintf(
                '(z.country = :%1$s AND z.postalCode IN(:%2$s) AND c.country = :%1$s)',
                $countryPlaceholder,
                $postalCodesPlaceholder
            );

            // Array keys turn "31000" into an int: bound as integers, MySQL would compare
            // postal_code numerically and could not use the zip_city index on it.
            $queryBuilder
                ->setParameter($countryPlaceholder, $countryId)
                ->setParameter($postalCodesPlaceholder, array_map(strval(...), array_keys($postalCodes)), ArrayParameterType::STRING);
            ++$i;
        }

        return $queryBuilder
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->getResult();
    }

    /**
     * Cities with at least one published event ending on or after $from, with that event count.
     *
     * @return iterable<array{slug: string, nb: int|string}>
     */
    public function findAllSitemap(DateTimeInterface $from): iterable
    {
        return parent::createQueryBuilder('c')
            ->select('c.slug, COUNT(e.id) AS nb')
            ->join(Place::class, 'p', 'WITH', 'p.city = c')
            ->join(Event::class, 'e', 'WITH', 'e.place = p')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('c.slug')
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return iterable<array{citySlug: string, tagId: int, tagSlug: string}>
     */
    public function findAllTagsSitemap(): iterable
    {
        // Tags from category relation
        yield from parent::createQueryBuilder('c')
            ->select('c.slug AS citySlug, cat.id AS tagId, cat.slug AS tagSlug')
            ->join(Place::class, 'p', 'WITH', 'p.city = c')
            ->join(Event::class, 'e', 'WITH', 'e.place = p')
            ->join('e.category', 'cat')
            ->where('e.endDate >= :from')
            ->setParameter('from', date('Y-m-d'))
            ->groupBy('c.slug, cat.id, cat.slug')
            ->getQuery()
            ->toIterable();

        // Tags from themes relation (getResult() because toIterable() forbids ManyToMany joins)
        yield from parent::createQueryBuilder('c')
            ->select('c.slug AS citySlug, t.id AS tagId, t.slug AS tagSlug')
            ->join(Place::class, 'p', 'WITH', 'p.city = c')
            ->join(Event::class, 'e', 'WITH', 'e.place = p')
            ->join('e.themes', 't')
            ->where('e.endDate >= :from')
            ->setParameter('from', date('Y-m-d'))
            ->groupBy('c.slug, t.id, t.slug')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    public function findAllRandomNames(?Country $country = null, int $limit = 5): array
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

    public function findOneBySlug(string $slug): ?City
    {
        return $this->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
