<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\OAuth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method OAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method OAuth[]    findAll()
 * @method OAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuth::class);
    }
}
