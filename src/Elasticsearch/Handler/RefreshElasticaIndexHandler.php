<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\Message\RefreshElasticaIndex;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshElasticaIndexHandler
{
    public function __construct(
        private IndexManager $indexManager,
    ) {
    }

    public function __invoke(RefreshElasticaIndex $message): void
    {
        $index = $this->indexManager->getIndex($message->getIndexName());
        $index->forcemerge(['max_num_segments' => 5]);
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }
}
