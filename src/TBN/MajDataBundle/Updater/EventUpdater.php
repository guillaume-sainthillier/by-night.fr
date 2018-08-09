<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 17/12/2016
 * Time: 14:28.
 */

namespace TBN\MajDataBundle\Updater;

use Doctrine\ORM\EntityManager;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Handler\EventHandler;
use TBN\MajDataBundle\Utils\Monitor;
use TBN\SocialBundle\Social\FacebookAdmin;

class EventUpdater extends Updater
{
    /**
     * @var EventHandler
     */
    protected $eventHandler;

    public function __construct(EntityManager $entityManager, FacebookAdmin $facebookAdmin, EventHandler $eventHandler)
    {
        parent::__construct($entityManager, $facebookAdmin);
        $this->eventHandler = $eventHandler;
    }

    public function update(\DateTime $since = null)
    {
        if (!$since) {
            $since = new \DateTime();
        }

        $repo  = $this->entityManager->getRepository('TBNAgendaBundle:Agenda');
        $count = $repo->getNextEventsCount($since);

        $fbIds   = $repo->getNextEventsFbIds($since);
        $fbStats = $this->facebookAdmin->getEventStatsFromIds($fbIds);

        unset($fbIds);

        $nbBatchs = \ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for ($i = 0; $i < $nbBatchs; ++$i) {
            $events = $repo->getNextEvents($since, $i, self::PAGINATION_SIZE);
            $this->doUpdate($events, $fbStats);
            $this->doFlush();
            Monitor::advanceProgressBar();
        }
    }

    protected function doUpdate(array $events, array $fbStats)
    {
        $downloadUrls = [];
        foreach ($events as $event) {
            /**
             * @var Agenda
             */
            $imageURL = $event->getUrl();
            $imageURL = \preg_replace('#(jp|jpe|pn)$#', '$1g', $imageURL);
            if ($event->getFacebookEventId() && isset($fbStats[$event->getFacebookEventId()])) {
                $imageURL = $fbStats[$event->getFacebookEventId()]['url'];
                $event->setFbParticipations($fbStats[$event->getFacebookEventId()]['participations']);
                $event->setFbInterets($fbStats[$event->getFacebookEventId()]['interets']);
            }

            if ($this->eventHandler->hasToDownloadImage($imageURL, $event)) {
                $event->setUrl($imageURL);
                $downloadUrls[$event->getId()] = $imageURL;
            }
        }

        $responses = $this->downloadUrls($downloadUrls);
        foreach ($events as $event) {
            if (isset($responses[$event->getId()])) {
                $this->eventHandler->uploadFile($event, $responses[$event->getId()]);
            }
        }
    }

    protected function doFlush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear(Agenda::class);
    }
}
