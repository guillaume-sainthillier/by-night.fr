<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 17/12/2016
 * Time: 14:28.
 */

namespace App\Updater;

use App\Entity\Event;
use App\Handler\EventHandler;
use App\Repository\EventRepository;
use App\Social\FacebookAdmin;
use App\Utils\Monitor;
use DateTime;
use Doctrine\Common\Persistence\ObjectManager;

class EventUpdater extends Updater
{
    const PAGINATION_SIZE = 5;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    public function __construct(ObjectManager $entityManager, FacebookAdmin $facebookAdmin, EventHandler $eventHandler)
    {
        parent::__construct($entityManager, $facebookAdmin);
        $this->eventHandler = $eventHandler;
    }

    public function update(DateTime $from)
    {
        /** @var EventRepository $repo */
        $repo = $this->entityManager->getRepository(Event::class);
        $count = $repo->getNextEventsCount($from);

        $nbBatchs = \ceil($count / self::PAGINATION_SIZE);
        Monitor::createProgressBar($nbBatchs);

        foreach (range(1, $nbBatchs) as $i) {
            $events = $repo->getNextEvents($from, $i, self::PAGINATION_SIZE);
            $fbIds = $this->extractFbIds($events);
            $fbStats = $this->facebookAdmin->getEventStatsFromIds($fbIds);

            dump($fbIds, $fbStats);
            die;
            $this->doUpdate($events, $fbStats);
            $this->doFlush();
            Monitor::advanceProgressBar();
        }
    }

    private function extractFbIds(array $events)
    {
        return array_filter(array_unique(array_map(function (Event $event) {
            return $event->getFacebookEventId();
        }, $events)));
    }

    protected function doUpdate(array $events, array $fbStats)
    {
        $downloadUrls = [];
        foreach ($events as $event) {
            /** @var Event $event */
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
        $this->entityManager->clear(Event::class);
    }
}
