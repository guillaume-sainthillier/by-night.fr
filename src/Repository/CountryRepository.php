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
use App\Dto\CountryDto;
use App\Entity\AdminZone1;
use App\Entity\AdminZone2;
use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 *
 * @implements DtoFindableRepositoryInterface<CountryDto, Country>
 *
 * @method Country|null find($id, $lockMode = null, $lockVersion = null)
 * @method Country|null findOneBy(array $criteria, array $orderBy = null)
 * @method Country[]    findAll()
 * @method Country[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CountryRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
    /**
     * Country rows are edited in the admin (postal code patterns): a bounded lifetime
     * keeps cached rows from outliving those edits, and any stale entry heals itself.
     */
    private const int RESULT_CACHE_LIFETIME = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getFromRegionOrDepartment(?string $region, ?string $department): ?Country
    {
        $qb = $this
            ->createQueryBuilder('c');

        if ($region) {
            $qb
                ->leftJoin(AdminZone1::class, 'admin_zone1', 'WITH', 'admin_zone1.country = c')
                ->orWhere('admin_zone1.name LIKE :region')
                ->setParameter('region', '%' . $region . '%');
        }

        if ($department) {
            $qb
                ->leftJoin(AdminZone2::class, 'admin_zone2', 'WITH', 'admin_zone2.country = c')
                ->orWhere('admin_zone2.name LIKE :department')
                ->setParameter('department', '%' . $department . '%');
        }

        return $qb
            ->groupBy('c')
            ->getQuery()
            ->enableResultCache(self::RESULT_CACHE_LIFETIME)
            ->useQueryCache(true)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByDtos(array $dtos, bool $eager): array
    {
        $wheres = [];
        $idsWheres = [];
        $namesWheres = [];

        foreach ($dtos as $dto) {
            // Match on the canonical code: SQLite compares ids case-sensitively, MySQL does
            // not, and the comparator that runs afterwards is strict either way.
            $code = $dto->getNormalizedCode();
            if (null !== $code) {
                $idsWheres[$code] = true;
            } elseif (null !== $dto->name && '' !== trim($dto->name)) {
                $namesWheres[$dto->name] = true;
            }
        }

        if ([] === $idsWheres && [] === $namesWheres) {
            return [];
        }

        $qb = $this->createQueryBuilder('c');

        if ([] !== $idsWheres) {
            $wheres[] = 'c.id IN (:ids)';
            $qb->setParameter('ids', array_keys($idsWheres));
        }

        if ([] !== $namesWheres) {
            $wheres[] = 'c.name IN(:names) OR c.displayName IN(:names) OR c.id IN(:names)';
            $qb->setParameter('names', array_keys($namesWheres));
        }

        return $qb
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->enableResultCache(self::RESULT_CACHE_LIFETIME)
            ->useQueryCache(true)
            ->execute();
    }
}
