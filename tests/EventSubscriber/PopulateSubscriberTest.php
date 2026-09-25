<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Elasticsearch\ElasticaMode;
use App\Elasticsearch\Pager\IdRangeCeilings;
use App\EventSubscriber\PopulateSubscriber;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\MessageBusInterface;

final class PopulateSubscriberTest extends TestCase
{
    public function testAPopulateCountsItsPagesDownFromTheHighestIdAtItsStart(): void
    {
        $ceilings = new IdRangeCeilings(new ArrayAdapter());
        // Stored by the previous populate
        $ceilings->get('event', static fn (): int => 100);

        $this->createSubscriber($ceilings)->preIndexPopulate(new PreIndexPopulateEvent('event', true, [
            'delete' => true,
            'reset' => true,
            'ignore_errors' => false,
            'sleep' => 0,
            'first_page' => 1,
            'max_per_page' => 5000,
            'pager_persister' => 'async',
        ]));

        self::assertSame(150, $ceilings->get('event', static fn (): int => 150));
    }

    private function createSubscriber(IdRangeCeilings $ceilings): PopulateSubscriber
    {
        $index = $this->createStub(Index::class);
        $index->method('getSettings')->willReturn($this->createStub(Settings::class));
        $indexManager = $this->createStub(IndexManager::class);
        $indexManager->method('getIndex')->willReturn($index);

        return new PopulateSubscriber($indexManager, new ElasticaMode(), $this->createStub(MessageBusInterface::class), $ceilings);
    }
}
