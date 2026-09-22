<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\ParserHistoryFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ParserHistoryCrudControllerTest extends WebTestCase
{
    public function testTheIndexListsEveryParserOfABatch(): void
    {
        $client = $this->createAdminClient();
        $history = ParserHistoryFactory::createOne(['fromData' => ['Open Agenda', 'Data Tourisme']]);

        $crawler = $client->request('GET', '/_administration/parser-history');

        self::assertResponseIsSuccessful();
        $row = $crawler->filter(\sprintf('tr[data-id="%d"]', $history->getId()));
        self::assertCount(1, $row);
        self::assertStringContainsString('Open Agenda', $row->text());
        self::assertStringContainsString('Data Tourisme', $row->text());
    }

    public function testTheSearchFindsTheBatchesOfAParser(): void
    {
        $client = $this->createAdminClient();
        $openAgenda = ParserHistoryFactory::createOne(['fromData' => ['Open Agenda']]);
        $dataTourisme = ParserHistoryFactory::createOne(['fromData' => ['Data Tourisme']]);

        $client->request('GET', '/_administration/parser-history', ['query' => 'Tourisme']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('tr[data-id="%d"]', $dataTourisme->getId()));
        self::assertSelectorNotExists(\sprintf('tr[data-id="%d"]', $openAgenda->getId()));
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin);

        return $client;
    }
}
