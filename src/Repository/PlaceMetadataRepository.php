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
     * External identities recorded on more than one row, whether on one place or on
     * several: exactly what the unique key on (external_id, external_origin) forbids.
     *
     * @return list<array{externalId: string, externalOrigin: string}>
     */
    public function findDuplicateKeys(?string $origin = null): array
    {
        $qb = $this
            ->createQueryBuilder('m')
            ->select('m.externalId AS externalId, m.externalOrigin AS externalOrigin')
            ->groupBy('m.externalId, m.externalOrigin')
            ->having('COUNT(m.id) > 1')
            ->orderBy('m.externalOrigin', 'ASC')
            ->addOrderBy('m.externalId', 'ASC');

        if (null !== $origin) {
            $qb
                ->where('m.externalOrigin = :origin')
                ->setParameter('origin', $origin);
        }

        $keys = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $keys[] = ['externalId' => (string) $row['externalId'], 'externalOrigin' => (string) $row['externalOrigin']];
        }

        return $keys;
    }
}
