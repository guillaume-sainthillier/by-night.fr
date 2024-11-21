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
 * @method Country|null find($id, $lockMode = null, $lockVersion = null)
 * @method Country|null findOneBy(array $criteria, array $orderBy = null)
 * @method Country[]    findAll()
 * @method Country[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class CountryRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
{
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
                ->orWhere('LOWER(admin_zone1.name) LIKE :region')
                ->setParameter('region', '%' . mb_strtolower($region) . '%');
        }

        if ($department) {
            $qb
                ->leftJoin(AdminZone2::class, 'admin_zone2', 'WITH', 'admin_zone2.country = c')
                ->orWhere('LOWER(admin_zone2.name) LIKE :department')
                ->setParameter('department', '%' . mb_strtolower($department) . '%');
        }

        return $qb
            ->groupBy('c')
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function findOneByName(?string $country): ?Country
    {
        return $this
            ->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :country OR LOWER(c.displayName) = :country OR c.id = :country')
            ->setParameter('country', mb_strtolower((string) $country))
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByDtos(array $dtos): array
    {
        $wheres = [];
        $idsWheres = [];
        $namesWheres = [];

        foreach ($dtos as $dto) {
            \assert($dto instanceof CountryDto);

            if (null !== $dto->code) {
                $idsWheres[$dto->code] = true;
            } elseif (null !== $dto->name) {
                $namesWheres[strtolower($dto->name)] = true;
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
            $wheres[] = 'LOWER(c.name) IN(:names) OR LOWER(c.displayName) IN(:names) OR c.id IN(:names)';
            $qb->setParameter('names', array_keys($namesWheres));
        }

        return $qb
            ->where(implode(' OR ', $wheres))
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->execute();
    }
}
