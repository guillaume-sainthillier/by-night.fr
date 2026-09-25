<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch\Handler;

use App\Elasticsearch\Handler\RefreshElasticaIndexHandler;
use App\Elasticsearch\Message\RefreshElasticaIndex;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Configuration\IndexConfigInterface;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\IndexManager;
use PHPUnit\Framework\TestCase;

final class RefreshElasticaIndexHandlerTest extends TestCase
{
    public function testTheRefreshIntervalOfTheIndexConfigurationIsRestored(): void
    {
        $this->handle(['index' => ['refresh_interval' => '5s']], expectedInterval: '5s');
    }

    public function testTheDefaultRefreshIntervalIsRestoredWhenTheIndexConfiguresNone(): void
    {
        $this->handle([], expectedInterval: Settings::DEFAULT_REFRESH_INTERVAL);
    }

    /**
     * @param array<string, mixed> $indexSettings
     */
    private function handle(array $indexSettings, string $expectedInterval): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects(self::once())->method('setRefreshInterval')->with($expectedInterval);
        $index = $this->createMock(Index::class);
        $index->expects(self::once())->method('forcemerge');
        $index->method('getSettings')->willReturn($settings);
        $indexManager = $this->createStub(IndexManager::class);
        $indexManager->method('getIndex')->willReturn($index);

        $indexConfig = $this->createStub(IndexConfigInterface::class);
        $indexConfig->method('getSettings')->willReturn($indexSettings);
        $configManager = $this->createStub(ConfigManager::class);
        $configManager->method('getIndexConfiguration')->willReturn($indexConfig);

        (new RefreshElasticaIndexHandler($indexManager, $configManager))(new RefreshElasticaIndex('event'));
    }
}
