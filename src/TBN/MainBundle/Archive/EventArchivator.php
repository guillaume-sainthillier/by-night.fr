<?php

namespace TBN\MainBundle\Archive;
use Doctrine\ORM\EntityManager;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 13/12/2016
 * Time: 19:08
 */
class EventArchivator
{
    /**
     * @var ObjectPersisterInterface
     */
    private $objectPersister;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager, ObjectPersisterInterface $objectPersister) {
        $this->entityManager = $entityManager;
        $this->objectPersister = $objectPersister;
    }

    public function archive() {
        $now = new \DateTime();
        $now->modify('-5 months');
        $events = $this->entityManager->getRepository('TBNAgendaBundle:Agenda')->findOlds($now);

        if($events) {
            $this->objectPersister->deleteMany($events);
            $this->entityManager->getRepository('TBNAgendaBundle:Agenda')->updateOlds($now);
        }
    }
}