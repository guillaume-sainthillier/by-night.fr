<?php

namespace App\Archive;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 13/12/2016
 * Time: 19:08.
 */
class EventArchivator
{
    const ITEMS_PER_TRANSACTION = 5000;

    /**
     * @var ObjectPersisterInterface
     */
    private $objectPersister;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, ObjectPersisterInterface $objectPersister)
    {
        $this->entityManager = $entityManager;
        $this->objectPersister = $objectPersister;
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function countObjects(QueryBuilder $queryBuilder)
    {
        /* Clone the query builder before altering its field selection and DQL,
         * lest we leave the query builder in a bad state for fetchSlice().
         */
        $qb = clone $queryBuilder;
        $rootAliases = $queryBuilder->getRootAliases();

        return $qb
            ->select($qb->expr()->count($rootAliases[0]))
            // Remove ordering for efficiency; it doesn't affect the count
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function archive()
    {
        /** @var EventRepository $repo */
        $repo = $this->entityManager->getRepository(Event::class);
        $qb = $repo->findNonIndexablesBuilder();
        $nbObjects = $this->countObjects($qb);

        $nbTransactions = \ceil($nbObjects / self::ITEMS_PER_TRANSACTION);
        Monitor::createProgressBar($nbTransactions);
        for ($i = 0; $i < $nbTransactions; ++$i) {
            $events = $qb
                ->setFirstResult($i * self::ITEMS_PER_TRANSACTION)
                ->setMaxResults(self::ITEMS_PER_TRANSACTION)
                ->getQuery()
                ->getResult();

            if (!\count($events)) {
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
}
