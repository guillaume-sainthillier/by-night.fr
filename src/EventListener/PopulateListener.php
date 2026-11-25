<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Elasticsearch\ElasticaMode;
use App\Elasticsearch\Handler\RefreshElasticaIndexHandler;
use App\Elasticsearch\Message\RefreshElasticaIndex;
use App\Elasticsearch\Pager\IdRangeCeilings;
use FOS\ElasticaBundle\Event\AbstractIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class PopulateListener
{
    public function __construct(
        private IndexManager $indexManager,
        private ElasticaMode $elasticaMode,
        private MessageBusInterface $messageBus,
        private IdRangeCeilings $idRangeCeilings,
        private RefreshElasticaIndexHandler $refreshElasticaIndexHandler,
    ) {
    }

    #[AsEventListener(event: PreIndexPopulateEvent::class)]
    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $index->getSettings()->setRefreshInterval('-1');

        // Each populate counts its pages down from the highest id at its start (see IdRangePager):
        // the pager provider, called right after this event, reads it again for the workers
        $this->idRangeCeilings->forget($event->getIndex());

        if (!$this->isAsync($event)) {
            $this->elasticaMode->setSynchronous(true);
        }
    }

    #[AsEventListener(event: PostIndexPopulateEvent::class)]
    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        if ($this->isAsync($event)) {
            $this->messageBus->dispatch(new RefreshElasticaIndex($event->getIndex()));

            return;
        }

        $this->elasticaMode->setSynchronous(false);

        ($this->refreshElasticaIndexHandler)(new RefreshElasticaIndex($event->getIndex()));
    }

    private function isAsync(AbstractIndexPopulateEvent $event): bool
    {
        return 'async' === $event->getOption('pager_persister');
    }
}
