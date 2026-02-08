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
use App\Entity\Place;
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
     * {@inheritDoc}
     *
     * @return Place[]
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        // Query 1: Fetch places without metadatas to avoid cartesian product
        // metadatas is still joined for WHERE clause matching (external IDs)
        $qb = $this
            ->createQueryBuilder('p')
            ->addSelect('city')
            ->addSelect('country')
            ->addSelect('cityCountry')
            ->leftJoin('p.metadatas', 'metadatas')
            ->leftJoin('p.city', 'city')
            ->leftJoin('p.country', 'country')
            ->leftJoin('city.country', 'cityCountry');

        // Eager mode: also match by city/country location (broader, loads more entities)
        if ($eager) {
            $cityWheres = [];
            $countryWheres = [];
            foreach ($dtos as $dto) {
                if (null !== $dto->city && null !== $dto->city->entityId) {
                    $cityWheres[$dto->city->entityId] = true;
                } elseif (null !== $dto->country && null !== $dto->country->entityId) {
                    $countryWheres[$dto->country->entityId] = true;
                }
            }

            $wheres = [];
            if ([] !== $cityWheres) {
                $wheres[] = 'p.city IN(:cities)';
                $qb->setParameter('cities', array_keys($cityWheres));
            }

            if ([] !== $countryWheres) {
                $wheres[] = 'p.country IN(:countries) AND p.city IS NULL';
                $qb->setParameter('countries', array_keys($countryWheres));
            }

            if ([] !== $wheres) {
                $qb->andWhere(implode(' OR ', $wheres));
            }
        } else {
            // Always match by external IDs (fast, indexed)
            $this->addDtosToQueryBuilder($qb, 'metadatas', $dtos);
        }

        if (0 === \count($qb->getParameters())) {
            return [];
        }

        /** @var Place[] $places */
        $places = $qb
            ->getQuery()
            ->execute();

        // Query 2: Batch load metadatas for all found places
        // This avoids the cartesian product issue while still loading metadatas
        if ([] !== $places) {
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
        }

        return $places;
    }

    /**
     * @return iterable<array>
     */
    public function findAllSitemap(): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->select('p.slug, c.slug AS city_slug')
            ->join('p.city', 'c')
            ->getQuery()
            ->toIterable();
    }
}
