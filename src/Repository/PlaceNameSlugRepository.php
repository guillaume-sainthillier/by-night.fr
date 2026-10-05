<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\PlaceNameSlug;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaceNameSlug>
 */
final class PlaceNameSlugRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaceNameSlug::class);
    }

    /**
     * @param list<int> $placeIds
     */
    public function countByPlaces(array $placeIds): int
    {
        if ([] === $placeIds) {
            return 0;
        }

        return (int) $this
            ->createQueryBuilder('x')
            ->select('COUNT(x.id)')
            ->where('x.place IN (:ids)')
            ->setParameter('ids', $placeIds)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
