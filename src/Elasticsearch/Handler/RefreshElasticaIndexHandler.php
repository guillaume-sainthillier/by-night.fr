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
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ends a populate: merges the segments and turns the refresh (disabled while populating) back on.
 */
#[AsMessageHandler]
final readonly class RefreshElasticaIndexHandler
{
    public function __construct(
        private IndexManager $indexManager,
        #[Autowire(service: 'fos_elastica.config_manager')]
        private ConfigManager $configManager,
    ) {
    }

    public function __invoke(RefreshElasticaIndex $message): void
    {
        $indexName = $message->getIndexName();
        $index = $this->indexManager->getIndex($indexName);
        $index->forcemerge(['max_num_segments' => 5]);
        // Back to the interval of the index configuration (fos_elastica.yaml), not Elasticsearch's default
        $index->getSettings()->setRefreshInterval(
            $this->configManager->getIndexConfiguration($indexName)->getSettings()['index']['refresh_interval'] ?? Settings::DEFAULT_REFRESH_INTERVAL
        );
    }
}
