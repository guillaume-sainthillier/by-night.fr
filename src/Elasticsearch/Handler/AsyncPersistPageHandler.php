<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\ElasticaMode;
use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Persister\AsyncPagerPersister;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AsyncPersistPageHandler
{
    public function __construct(
        private AsyncPagerPersister $persister,
        private ElasticaMode $elasticaMode,
    ) {
    }

    public function __invoke(AsyncPersistPage $message): void
    {
        $this->elasticaMode->setSynchronous(true);
        try {
            $this->persister->insertPage($message->getPage(), $message->getOptions());
        } finally {
            $this->elasticaMode->setSynchronous(false);
        }
    }
}
