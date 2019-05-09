<?php

namespace App\EventListener;

use FOS\ElasticaBundle\Event\IndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulateListener implements EventSubscriberInterface
{
    /**
     * @var IndexManager
     */
    private $indexManager;

    /**
     * @param IndexManager $indexManager
     */
    public function __construct(IndexManager $indexManager)
    {
        $this->indexManager = $indexManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            IndexPopulateEvent::PRE_INDEX_POPULATE => 'preIndexPopulate',
            IndexPopulateEvent::POST_INDEX_POPULATE => 'postIndexPopulate',
        ];
    }

    public function preIndexPopulate(IndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        $settings->setRefreshInterval(-1);
    }

    public function postIndexPopulate(IndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        $index->forcemerge(['max_num_segments' => 5]);
        $settings->setRefreshInterval('1s');
    }
}
