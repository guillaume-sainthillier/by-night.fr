<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\User;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findSiteMap()
    {
        return $this->createQueryBuilder('u')
            ->getQuery()
            ->iterate();
    }

    public function getUserFbIdsCount(DateTimeInterface $from)
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(i.facebook_id)')
            ->join('u.info', 'i')
            ->where('u.updatedAt >= :from')
            ->andWhere('i.facebook_id IS NOT NULL')
            ->andWhere('u.path IS NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUsersWithInfo(DateTimeInterface $from, int $page, int $limit)
    {
        return $this->createQueryBuilder('u')
            ->select('u', 'i')
            ->join('u.info', 'i')
            ->where('u.updatedAt >= :from')
            ->andWhere('i.facebook_id IS NOT NULL')
            ->andWhere('u.path IS NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTopMembres($page = 1, $limit = 7)
    {
        return $this->createQueryBuilder('u')
            ->select('u')
            ->addSelect('i')
            ->addSelect('COUNT(u.id) AS nb_events')
            ->leftJoin('u.info', 'i')
            ->leftJoin('u.calendriers', 'c')
            ->orderBy('nb_events', 'DESC')
            ->groupBy('u.id')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function findMembresCount()
    {
        return $this->_em
            ->createQueryBuilder()
            ->select('count(u.id)')
            ->from('App:User', 'u')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneBySocial(string $email, string $infoPrefix, $socialId): ?User
    {
        return $this
            ->createQueryBuilder('u')
            ->select('u')
            ->addSelect('i')
            ->leftJoin('u.info', 'i')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->orWhere(sprintf('i.%s_id = :socialId', $infoPrefix))
            ->setParameter('socialId', $socialId)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }
}
