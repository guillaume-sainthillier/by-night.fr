<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 17/12/2016
 * Time: 14:28
 */

namespace TBN\MajDataBundle\Utils;


use Doctrine\ORM\EntityManager;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\SocialBundle\Social\FacebookAdmin;

class EventUpdater extends Updater
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var EventHandler
     */
    private $eventHandler;

    /**
     * @var FacebookAdmin
     */
    private $facebookAdmin;

    public function __construct(EntityManager $entityManager, EventHandler $eventHandler, FacebookAdmin $facebookAdmin)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->eventHandler = $eventHandler;
        $this->facebookAdmin = $facebookAdmin;
    }

    public function update(\DateTime $since = null) {
        if(! $since) {
            $since = new \DateTime();
        }

        $repo = $this->entityManager->getRepository('TBNAgendaBundle:Agenda');
        $count = $repo->getNextEventsCount($since);

        $fbIds = $repo->getNextEventsFbIds($since);
        $fbStats = $this->facebookAdmin->getEventStatsFromIds($fbIds);

        unset($fbIds);

        $nbBatchs = ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        for($i = 0; $i < $nbBatchs; $i++) {
            $events = $repo->getNextEvents($since, $i, self::PAGINATION_SIZE);
            $this->doUpdate($events, $fbStats);
            $this->doFlush();
            Monitor::advanceProgressBar();
        }
    }

    protected function doUpdate(array $events, array $fbStats) {
        $downloadUrls = [];
        foreach($events as $event) {
            /**
             * @var Agenda $event
             */
            $imageURL = $event->getUrl();
            $imageURL = preg_replace("#(jp|jpe|pn)$#", "$1g", $imageURL);
            if($event->getFacebookEventId() && isset($fbStats[$event->getFacebookEventId()])) {
                $imageURL = $fbStats[$event->getFacebookEventId()]['url'];
                $event->setFbParticipations($fbStats[$event->getFacebookEventId()]['participations']);
                $event->setFbInterets($fbStats[$event->getFacebookEventId()]['interets']);
            }

            if($this->eventHandler->hasToDownloadImage($imageURL, $event)) {
                $event->setUrl($imageURL);
                $downloadUrls[$event->getId()] = $imageURL;
            }
        }

        $responses = $this->downloadUrls($downloadUrls);
        foreach($events as $event) {
            if(isset($responses[$event->getId()])) {
                $this->eventHandler->uploadFile($event, $responses[$event->getId()]);
            }
        }
    }



    protected function doFlush() {
        $this->entityManager->flush();
        $this->entityManager->clear(Agenda::class);
    }
}