<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\ParserState;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParserState>
 *
 * @method ParserState|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParserState|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParserState[]    findAll()
 * @method ParserState[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ParserStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParserState::class);
    }

    /**
     * When the last successful run of this parser started, null if it never ran.
     */
    public function findLastParsedAt(string $parser): ?DateTimeImmutable
    {
        return $this->findOneBy(['parser' => $parser])?->getLastParsedAt();
    }

    /**
     * Records a successful run: the next incremental import starts from $startedAt.
     */
    public function markParsed(string $parser, DateTimeImmutable $startedAt): void
    {
        $state = $this->findOneBy(['parser' => $parser]);
        if (null === $state) {
            $state = new ParserState($parser, $startedAt);
            $this->getEntityManager()->persist($state);
        } else {
            $state->setLastParsedAt($startedAt);
        }

        $this->getEntityManager()->flush();
    }
}
