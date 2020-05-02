<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\HistoriqueMaj;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HistoriqueMaj|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueMaj|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoriqueMaj[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueMajRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueMaj::class);
    }

    public function findAll()
    {
        return $this->findBy([], ['id' => 'DESC']);
    }
}
