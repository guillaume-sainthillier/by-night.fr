<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Archive;

use App\Repository\EventRepository;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

final readonly class EventArchivator
{
    public const int ITEMS_PER_TRANSACTION = 5_000;

    public function __construct(private EntityManagerInterface $entityManager, private ObjectPersisterInterface $objectPersister, private EventRepository $eventRepository)
    {
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function archive(): void
    {
        $repo = $this->eventRepository;
        $qb = $repo->findNonIndexablesBuilder();
        $nbObjects = $this->countObjects($qb);

        $nbTransactions = (int) ceil($nbObjects / self::ITEMS_PER_TRANSACTION);
        Monitor::createProgressBar($nbTransactions);
        for ($i = 0; $i < $nbTransactions; ++$i) {
            $events = $qb
                ->setFirstResult($i * self::ITEMS_PER_TRANSACTION)
                ->setMaxResults(self::ITEMS_PER_TRANSACTION)
                ->getQuery()
                ->getResult();

            if (0 === (is_countable($events) ? \count($events) : 0)) {
                continue;
            }

            $this->objectPersister->deleteMany($events);
            Monitor::advanceProgressBar();
            unset($events);
            $this->entityManager->clear();
        }

        $repo->updateNonIndexables();
        Monitor::finishProgressBar();
    }

    /**
     * @throws NonUniqueResultException
     */
    private function countObjects(QueryBuilder $queryBuilder): int
    {
        /* Clone the query builder before altering its field selection and DQL,
         * lest we leave the query builder in a bad state for fetchSlice().
         */
        $qb = clone $queryBuilder;
        $rootAliases = $queryBuilder->getRootAliases();

        return (int) $qb
            ->select($qb->expr()->count($rootAliases[0]))
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
