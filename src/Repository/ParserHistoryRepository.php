<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ParserHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;

/**
 * @extends ServiceEntityRepository<ParserHistory>
 *
 * @method ParserHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParserHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParserHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ParserHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParserHistory::class);
    }

    /**
     * @return ParserHistory[]
     */
    #[Override]
    public function findAll(): array
    {
        return $this->findBy([], ['id' => 'DESC']);
    }
}
