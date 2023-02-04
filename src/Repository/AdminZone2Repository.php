<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\AdminZone2;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AdminZone2|null find($id, $lockMode = null, $lockVersion = null)
 * @method AdminZone2|null findOneBy(array $criteria, array $orderBy = null)
 * @method AdminZone2[]    findAll()
 * @method AdminZone2[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdminZone2Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminZone2::class);
    }
}
