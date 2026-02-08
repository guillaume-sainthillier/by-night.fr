<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Elasticsearch\ElasticaMode;
use App\Elasticsearch\Message\RefreshElasticaIndex;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Event\AbstractIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class PopulateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IndexManager $indexManager,
        private ElasticaMode $elasticaMode,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $index->getSettings()->setRefreshInterval('-1');

        if (!$this->isAsync($event)) {
            $this->elasticaMode->setSynchronous(true);
        }
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        if ($this->isAsync($event)) {
            $this->messageBus->dispatch(new RefreshElasticaIndex($event->getIndex()));

            return;
        }

        $this->elasticaMode->setSynchronous(false);

        $index = $this->indexManager->getIndex($event->getIndex());
        $index->forcemerge(['max_num_segments' => 5]);
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }

    private function isAsync(AbstractIndexPopulateEvent $event): bool
    {
        return 'async' === $event->getOption('pager_persister');
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
        ];
    }
}
