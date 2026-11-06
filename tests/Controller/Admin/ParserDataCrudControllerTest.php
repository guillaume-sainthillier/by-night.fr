<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\ParserDataFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ParserDataCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsParserData(): void
    {
        $client = $this->createAdminClient();
        ParserDataFactory::createOne(['externalId' => 'ext1', 'reason' => 1]);
        ParserDataFactory::createOne(['externalId' => 'ext2', 'reason' => 2]);

        $client->request('GET', '/_administration/parser-data');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'ext1');
        self::assertSelectorTextContains('table', 'ext2');
    }

    public function testDetailShowsParserData(): void
    {
        $client = $this->createAdminClient();
        $data = ParserDataFactory::createOne(['firewallVersion' => '2.0']);

        $client->request('GET', \sprintf('/_administration/parser-data/%d', $data->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '2.0');
    }

    public function testEditingParserDataWorks(): void
    {
        $client = $this->createAdminClient();
        $data = ParserDataFactory::createOne(['reason' => 1, 'parserVersion' => '1.0']);

        $crawler = $client->request('GET', \sprintf('/_administration/parser-data/%d/edit', $data->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, [
            'ParserData[reason]' => 2,
            'ParserData[parserVersion]' => '2.0',
        ]);

        self::assertResponseRedirects();
        $data = ParserDataFactory::find(['id' => $data->getId()]);
        self::assertSame(2, $data->getReason());
        self::assertSame('2.0', $data->getParserVersion());
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
