<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\PlaceMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaceMetadata>
 *
 * @method PlaceMetadata|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlaceMetadata|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlaceMetadata[]    findAll()
 * @method PlaceMetadata[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PlaceMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaceMetadata::class);
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
