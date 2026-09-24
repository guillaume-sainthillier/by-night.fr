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
use App\Dto\PlaceDto;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\PlaceMetadata;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Place>
 *
 * @implements DtoFindableRepositoryInterface<PlaceDto, Place>
 *
 * @method Place|null find($id, $lockMode = null, $lockVersion = null)
 * @method Place|null findOneBy(array $criteria, array $orderBy = null)
 * @method Place[]    findAll()
 * @method Place[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PlaceRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
    use DtoFindableTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Place::class);
    }

    /**
     * Default cap for the city-wide fuzzy fallback (see findAllByCityBounded).
     */
    public const int DEFAULT_CITY_FALLBACK_LIMIT = 2000;

    /**
     * {@inheritDoc}
     *
     * @return Place[]
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        if ($eager) {
            // Eager (location) matching is delegated to the bounded city-wide loader.
            [$cityIds, $countryIds] = $this->extractLocationIds($dtos);

            return $this->findAllByCityBounded($cityIds, $countryIds, self::DEFAULT_CITY_FALLBACK_LIMIT);
        }

        // Match by external IDs (fast, indexed). metadatas is joined only for the
        // WHERE clause; it is batch-loaded afterwards to avoid a cartesian product.
        $qb = $this
            ->createQueryBuilder('p')
            ->addSelect('city')
            ->addSelect('country')
            ->addSelect('cityCountry')
            ->leftJoin('p.metadatas', 'metadatas')
            ->leftJoin('p.city', 'city')
            ->leftJoin('p.country', 'country')
            ->leftJoin('city.country', 'cityCountry');

        $this->addDtosToQueryBuilder($qb, 'metadatas', $dtos);

        if (0 === \count($qb->getParameters())) {
            return [];
        }

        /** @var Place[] $places */
        $places = $qb
            ->getQuery()
            ->execute();

        $this->hydrateCollections($places);

        return $places;
    }

    /**
     * Narrow, indexed lookup by normalized name slug — the de-duplication fast path.
     *
     * @param array<int, string[]>    $cityGroups    cityId => normalized slugs
     * @param array<string, string[]> $countryGroups countryCode => normalized slugs (city-less places)
     *
     * @return Place[]
     */
    public function findAllByNameSlugs(array $cityGroups, array $countryGroups): array
    {
        if ([] === $cityGroups && [] === $countryGroups) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('p')
            ->addSelect('city')
            ->addSelect('country')
            ->addSelect('cityCountry')
            ->join('p.nameSlugs', 'nameSlug')
            ->leftJoin('p.city', 'city')
            ->leftJoin('p.country', 'country')
            ->leftJoin('city.country', 'cityCountry');

        $wheres = [];
        $i = 0;
        foreach ($cityGroups as $cityId => $slugs) {
            $wheres[] = \sprintf('(nameSlug.city = :nsCity%d AND nameSlug.slug IN (:nsSlugs%d))', $i, $i);
            $qb->setParameter('nsCity' . $i, $cityId);
            $qb->setParameter('nsSlugs' . $i, $slugs);
            ++$i;
        }

        foreach ($countryGroups as $countryId => $slugs) {
            $wheres[] = \sprintf('(nameSlug.country = :nsCountry%d AND nameSlug.city IS NULL AND nameSlug.slug IN (:nsSlugs%d))', $i, $i);
            $qb->setParameter('nsCountry' . $i, $countryId);
            $qb->setParameter('nsSlugs' . $i, $slugs);
            ++$i;
        }

        /** @var Place[] $places */
        $places = $qb
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->execute();

        $this->hydrateCollections($places);

        return $places;
    }

    /**
     * Bounded city-wide load, used only as the fuzzy fallback when external-id and
     * slug lookups both miss. Capped to avoid loading an entire large city.
     *
     * @param int[]    $cityIds
     * @param string[] $countryIds
     *
     * @return Place[]
     */
    public function findAllByCityBounded(array $cityIds, array $countryIds, int $limit): array
    {
        $wheres = [];
        $qb = $this
            ->createQueryBuilder('p')
            ->addSelect('city')
            ->addSelect('country')
            ->addSelect('cityCountry')
            ->leftJoin('p.city', 'city')
            ->leftJoin('p.country', 'country')
            ->leftJoin('city.country', 'cityCountry');

        if ([] !== $cityIds) {
            $wheres[] = 'p.city IN(:cities)';
            $qb->setParameter('cities', $cityIds);
        }

        if ([] !== $countryIds) {
            $wheres[] = '(p.country IN(:countries) AND p.city IS NULL)';
            $qb->setParameter('countries', $countryIds);
        }

        if ([] === $wheres) {
            return [];
        }

        /** @var Place[] $places */
        $places = $qb
            ->andWhere(implode(' OR ', $wheres))
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();

        $this->hydrateCollections($places);

        return $places;
    }

    /**
     * @param PlaceDto[] $dtos
     *
     * @return array{0: list<int>, 1: list<string>} [cityIds, countryCodes]
     */
    private function extractLocationIds(array $dtos): array
    {
        $cityIds = [];
        $countryIds = [];
        foreach ($dtos as $dto) {
            if (null !== $dto->city && null !== $dto->city->entityId) {
                $cityIds[$dto->city->entityId] = true;
            } elseif (null !== $dto->country && null !== $dto->country->entityId) {
                $countryIds[$dto->country->entityId] = true;
            }
        }

        return [array_keys($cityIds), array_keys($countryIds)];
    }

    /**
     * Ids of the places no event points to, created before $createdBefore and, when
     * $origin is given, carrying at least one identity from that external origin.
     *
     * @return list<int>
     */
    public function findEventlessIds(?string $origin, DateTimeImmutable $createdBefore): array
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->select('p.id')
            ->where(\sprintf('NOT EXISTS (SELECT e.id FROM %s e WHERE e.place = p)', Event::class))
            ->andWhere('p.createdAt <= :createdBefore')
            ->setParameter('createdBefore', $createdBefore)
            ->orderBy('p.id', 'ASC');

        if (null !== $origin) {
            $qb
                ->andWhere(\sprintf('EXISTS (SELECT m.id FROM %s m WHERE m.place = p AND m.externalOrigin = :origin)', PlaceMetadata::class))
                ->setParameter('origin', $origin);
        }

        return array_map(intval(...), $qb->getQuery()->getSingleColumnResult());
    }

    /**
     * The given place ids that still have no event.
     *
     * @param list<int> $placeIds
     *
     * @return list<int>
     */
    public function onlyEventless(array $placeIds): array
    {
        if ([] === $placeIds) {
            return [];
        }

        $ids = $this
            ->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.id IN (:ids)')
            ->andWhere(\sprintf('NOT EXISTS (SELECT e.id FROM %s e WHERE e.place = p)', Event::class))
            ->setParameter('ids', $placeIds)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(intval(...), $ids);
    }

    /**
     * Batch-load metadatas and name slugs for the found places (separate queries to
     * avoid a cartesian product), so downstream matching/storing stays in-memory.
     *
     * @param Place[] $places
     */
    private function hydrateCollections(array $places): void
    {
        if ([] === $places) {
            return;
        }

        $placeIds = array_map(static fn (Place $place) => $place->getId(), $places);

        $this
            ->createQueryBuilder('p')
            ->select('PARTIAL p.{id}')
            ->addSelect('metadatas')
            ->leftJoin('p.metadatas', 'metadatas')
            ->where('p.id IN(:placeIds)')
            ->setParameter('placeIds', $placeIds)
            ->getQuery()
            ->execute();

        $this
            ->createQueryBuilder('p')
            ->select('PARTIAL p.{id}')
            ->addSelect('nameSlug')
            ->leftJoin('p.nameSlugs', 'nameSlug')
            ->where('p.id IN(:placeIds)')
            ->setParameter('placeIds', $placeIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Places hosting at least one published event ending on or after $from.
     *
     * @return iterable<array{slug: string, city_slug: string}>
     */
    public function findAllSitemap(DateTimeInterface $from): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->select('p.slug, c.slug AS city_slug')
            ->join('p.city', 'c')
            ->join(Event::class, 'e', 'WITH', 'e.place = p')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->andWhere('p.slug IS NOT NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('p.slug, c.slug')
            ->getQuery()
            ->toIterable();
    }
}
