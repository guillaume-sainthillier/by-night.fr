<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\AppOAuth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppOAuth>
 *
 * @method AppOAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppOAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppOAuth[]    findAll()
 * @method AppOAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class AppOAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppOAuth::class);
    }
}
