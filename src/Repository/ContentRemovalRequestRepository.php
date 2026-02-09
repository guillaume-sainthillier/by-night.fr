<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ContentRemovalRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentRemovalRequest>
 *
 * @method ContentRemovalRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContentRemovalRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContentRemovalRequest[]    findAll()
 * @method ContentRemovalRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ContentRemovalRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentRemovalRequest::class);
    }
}
