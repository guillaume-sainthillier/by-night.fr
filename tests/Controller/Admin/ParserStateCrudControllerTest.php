<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\ParserStateFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ParserStateCrudControllerTest extends WebTestCase
{
    public function testIndexListsTheWatermarkOfEachParser(): void
    {
        $client = $this->createAdminClient();
        ParserStateFactory::createOne(['parser' => 'openagenda']);
        ParserStateFactory::createOne(['parser' => 'datatourisme']);

        $client->request('GET', '/_administration/parser-state');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'openagenda');
        self::assertSelectorTextContains('table', 'datatourisme');
    }

    public function testEditRewindsTheWatermarkButNotTheParser(): void
    {
        $client = $this->createAdminClient();
        $state = ParserStateFactory::createOne([
            'parser' => 'openagenda',
            'lastParsedAt' => new DateTimeImmutable('2026-09-21 03:00:00'),
        ]);

        $client->request('GET', \sprintf('/_administration/parser-state/%d/edit', $state->getId()));
        self::assertSelectorExists('input[name="ParserState[parser]"][disabled]');

        $client->submitForm('Sauvegarder les modifications', [
            'ParserState[lastParsedAt]' => '2026-09-14T03:00',
        ]);

        self::assertResponseRedirects();
        $state = ParserStateFactory::find(['parser' => 'openagenda']);
        self::assertEquals(new DateTimeImmutable('2026-09-14 03:00:00'), $state->getLastParsedAt());
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
