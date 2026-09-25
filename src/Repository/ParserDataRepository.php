<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ParserData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParserData>
 *
 * @method ParserData|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParserData|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParserData[]    findAll()
 * @method ParserData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ParserDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParserData::class);
    }

    /**
     * What the change detection compares, for the given events of one source, as plain
     * arrays: an import checks every event it reads, and hydrated entities would pile up
     * in the unit of work for the whole run.
     *
     * @param list<string> $externalIds
     *
     * @return array<string, array{contentHash: ?string, firewallVersion: ?string, parserVersion: ?string}> by external id
     */
    public function findSignatures(string $externalOrigin, array $externalIds): array
    {
        if ([] === $externalIds) {
            return [];
        }

        /** @var list<array{externalId: string, contentHash: ?string, firewallVersion: ?string, parserVersion: ?string}> $rows */
        $rows = $this
            ->createQueryBuilder('p')
            ->select('p.externalId, p.contentHash, p.firewallVersion, p.parserVersion')
            ->where('p.externalOrigin = :externalOrigin')
            ->andWhere('p.externalId IN (:externalIds)')
            ->setParameter('externalOrigin', $externalOrigin)
            // Numeric ids must stay strings, see DtoFindableTrait
            ->setParameter('externalIds', $externalIds, ArrayParameterType::STRING)
            ->getQuery()
            ->getArrayResult();

        $signatures = [];
        foreach ($rows as ['externalId' => $externalId, 'contentHash' => $contentHash, 'firewallVersion' => $firewallVersion, 'parserVersion' => $parserVersion]) {
            $signatures[$externalId] = ['contentHash' => $contentHash, 'firewallVersion' => $firewallVersion, 'parserVersion' => $parserVersion];
        }

        return $signatures;
    }

    /**
     * @param list<string> $externalIds
     *
     * @return ParserData[]
     */
    public function findByExternalIds(string $externalOrigin, array $externalIds): array
    {
        if ([] === $externalIds) {
            return [];
        }

        return $this
            ->createQueryBuilder('p')
            ->where('p.externalOrigin = :externalOrigin')
            ->andWhere('p.externalId IN (:externalIds)')
            ->setParameter('externalOrigin', $externalOrigin)
            // Numeric ids must stay strings, see DtoFindableTrait
            ->setParameter('externalIds', $externalIds, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
    }
}
