<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use Elastica\Index\Settings;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulateSubscriber implements EventSubscriberInterface
{
    public function __construct(private IndexManager $indexManager)
    {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        $settings->setRefreshInterval(-1);
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $index->getClient()->request('_forcemerge', 'POST', [], ['max_num_segments' => 5]);
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
        ];
    }
}
