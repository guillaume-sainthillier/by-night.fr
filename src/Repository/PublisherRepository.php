<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Publisher;
use App\Entity\User;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

/**
 * @extends ServiceEntityRepository<Publisher>
 *
 * @method Publisher|null find($id, $lockMode = null, $lockVersion = null)
 * @method Publisher|null findOneBy(array $criteria, array $orderBy = null)
 * @method Publisher[]    findAll()
 * @method Publisher[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PublisherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publisher::class);
    }

    /**
     * The publishers whose page is worth indexing: those with an event online to come.
     *
     * @return CursorPagination<array{slug: string, id: int, updatedAt: ?DateTimeImmutable}>
     */
    public function findAllSitemap(DateTimeInterface $from, int $batchSize): CursorPagination
    {
        $queryBuilder = $this
            ->createQueryBuilder('p')
            ->select('p.id, p.slug, p.updatedAt')
            ->join(Event::class, 'e', Join::WITH, 'e.publisher = p')
            ->where('e.endDate >= :from')
            ->andWhere('e.duplicateOf IS NULL')
            ->andWhere('e.draft = false')
            ->setParameter('from', $from->format('Y-m-d'))
            ->groupBy('p.id, p.slug, p.updatedAt');

        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('p.id', static fn (array $publisher): int => $publisher['id'])),
            $batchSize,
            fetchJoinCollection: false,
        );
    }

    /**
     * @return list<Publisher> the member's, by name
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['name' => 'ASC']);
    }

    /**
     * The publisher the member publishes as: the first one they own. Null until they publish.
     */
    public function findOneByOwner(User $owner): ?Publisher
    {
        return $this->findOneBy(['owner' => $owner], ['id' => 'ASC']);
    }
}
