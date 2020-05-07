<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\SiteInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SiteInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method SiteInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method SiteInfo[]    findAll()
 * @method SiteInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiteInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteInfo::class);
    }
}
