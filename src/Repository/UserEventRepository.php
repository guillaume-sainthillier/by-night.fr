<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\UserEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEvent>
 *
 * @method UserEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserEvent[]    findAll()
 * @method UserEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEvent::class);
    }
}
