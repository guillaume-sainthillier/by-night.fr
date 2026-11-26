<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Follow;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Follow>
 */
final class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    public function findOneByTarget(User $user, Publisher|Place $target): ?Follow
    {
        return $this->findOneBy(['user' => $user, $target instanceof Publisher ? 'publisher' : 'place' => $target]);
    }

    public function countFollowers(Publisher|Place $target): int
    {
        return $this->count([$target instanceof Publisher ? 'publisher' : 'place' => $target]);
    }

    /**
     * The publishers and the venues a member follows, with them, the latest followed first.
     *
     * @return list<Follow>
     */
    public function findAllOfUser(User $user): array
    {
        /* @var list<Follow> */
        return $this
            ->createQueryBuilder('f')
            ->addSelect('publisher', 'place')
            ->leftJoin('f.publisher', 'publisher')
            ->leftJoin('f.place', 'place')
            ->where('f.user = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('f.createdAt', SortDirection::Descending)
            ->addOrderBy('f.id', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
