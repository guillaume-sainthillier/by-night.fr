<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ParserData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ParserData|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParserData|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParserData[]    findAll()
 * @method ParserData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParserDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParserData::class);
    }
}
