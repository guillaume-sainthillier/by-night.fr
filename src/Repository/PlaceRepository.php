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
use App\Dto\PlaceDto;
use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Place|null find($id, $lockMode = null, $lockVersion = null)
 * @method Place|null findOneBy(array $criteria, array $orderBy = null)
 * @method Place[]    findAll()
 * @method Place[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlaceRepository extends ServiceEntityRepository implements DtoFindableRepositoryInterface
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
            ->leftJoin('city.country', 'cityCountry')
        ;

        $wheres = [];
        $alreadyAdded = [];
        $i = 1;
        foreach ($dtos as $dto) {
            \assert($dto instanceof PlaceDto);

            if (null !== $dto->city && null !== $dto->city->id) {
                $key = sprintf('city.%s', $dto->city->id);
                if (isset($alreadyAdded[$key])) {
                    continue;
                }
                $alreadyAdded[$key] = true;
                $cityPlaceholder = sprintf('city_%d', $i);
                $wheres[] = sprintf('p.city = :%s', $cityPlaceholder);
                $qb->setParameter($cityPlaceholder, $dto->city->id);
                ++$i;
            } elseif (null !== $dto->country && null !== $dto->country->id) {
                $key = sprintf('country.%s', $dto->country->id);
                if (isset($alreadyAdded[$key])) {
                    continue;
                }
                $countryPlaceholder = sprintf('country_%d', $i);
                $wheres[] = sprintf('(p.country = :%s AND p.city IS NULL)', $countryPlaceholder);
                $qb->setParameter($countryPlaceholder, $dto->country->id);
                ++$i;
            }
        }

        if (\count($wheres) > 0) {
            $qb->orWhere(implode(' OR ', $wheres));
        }

        $this->addDtosToQueryBuilding($qb, 'metadatas', $dtos);

        return $qb
            ->getQuery()
            ->execute();
    }

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
