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
    public function findAllByDtos(array $dtos): array
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->addSelect('metadatas')
            ->addSelect('city')
            ->addSelect('country')
            ->addSelect('cityCountry')
            ->leftJoin('p.metadatas', 'metadatas')
            ->leftJoin('p.city', 'city')
            ->leftJoin('p.country', 'country')
            ->leftJoin('city.country', 'cityCountry');

        $cityWheres = [];
        $countryWheres = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof PlaceDto);

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

        $this->addDtosToQueryBuilder($qb, 'metadatas', $dtos);

        if (0 === \count($qb->getParameters())) {
            return [];
        }

        return $qb
            ->getQuery()
            ->execute();
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
