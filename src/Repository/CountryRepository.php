<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
 * @method Country|null find($id, $lockMode = null, $lockVersion = null)
 * @method Country|null findOneBy(array $criteria, array $orderBy = null)
 * @method Country[]    findAll()
 * @method Country[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CountryRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
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
            ->createQueryBuilder('c')
            ->leftJoin(AdminZone1::class, 'admin_zone1', 'WITH', 'admin_zone1.country = c')
            ->leftJoin(AdminZone2::class, 'admin_zone2', 'WITH', 'admin_zone2.country = c');

        if ($region) {
            $qb
                ->orWhere('LOWER(admin_zone1.name) LIKE :region')
                ->setParameter('region', '%' . mb_strtolower($region) . '%');
        }

        if ($department) {
            $qb
                ->orWhere('LOWER(admin_zone2.name) LIKE :department')
                ->setParameter('department', '%' . mb_strtolower($department) . '%');
        }

        return $qb
            ->groupBy('c')
            ->getQuery()
            ->enableResultCache()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    public function findOneByName(?string $country): ?Country
    {
        return $this
            ->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :country OR LOWER(c.displayName) = :country OR c.id = :country')
            ->setParameter('country', mb_strtolower($country))
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

        if (0 === \count($idsWheres) && 0 === \count($namesWheres)) {
            return [];
        }

        $qb = $this->createQueryBuilder('c');

        if (\count($idsWheres) > 0) {
            $wheres[] = 'c.id IN (:ids)';
            $qb->setParameter('ids', array_keys($idsWheres));
        }

        if (\count($namesWheres) > 0) {
            $wheres[] = 'LOWER(c.name) IN(:names) OR LOWER(c.displayName) IN(:names) OR (c.id IN :names)';
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
