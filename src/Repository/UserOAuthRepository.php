<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\UserOAuth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserOAuth>
 * @method UserOAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserOAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserOAuth[]    findAll()
 * @method UserOAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserOAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOAuth::class);
    }
}
