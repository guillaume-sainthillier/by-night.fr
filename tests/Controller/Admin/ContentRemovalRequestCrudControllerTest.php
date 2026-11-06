<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Factory\ContentRemovalRequestFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ContentRemovalRequestCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsRequests(): void
    {
        $client = $this->createAdminClient();
        ContentRemovalRequestFactory::createOne(['email' => 'alice@example.test', 'type' => ContentRemovalType::Event]);
        ContentRemovalRequestFactory::createOne(['email' => 'bob@example.test', 'type' => ContentRemovalType::Image]);

        $client->request('GET', '/_administration/content-removal-request');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'alice@example.test');
        self::assertSelectorTextContains('table', 'bob@example.test');
    }

    public function testDetailShowsRequest(): void
    {
        $client = $this->createAdminClient();
        $request = ContentRemovalRequestFactory::createOne(['message' => 'Supprimez ceci svp']);

        $client->request('GET', \sprintf('/_administration/content-removal-request/%d', $request->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Supprimez ceci svp');
    }

    public function testEditingARequestWorks(): void
    {
        $client = $this->createAdminClient();
        $request = ContentRemovalRequestFactory::createOne(['message' => 'Ancien message', 'status' => ContentRemovalRequestStatus::Pending]);

        $crawler = $client->request('GET', \sprintf('/_administration/content-removal-request/%d/edit', $request->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, [
            'ContentRemovalRequest[message]' => 'Nouveau message',
            'ContentRemovalRequest[status]' => ContentRemovalRequestStatus::Processed->value,
        ]);

        self::assertResponseRedirects();
        $request = ContentRemovalRequestFactory::find(['id' => $request->getId()]);
        self::assertSame('Nouveau message', $request->getMessage());
        self::assertSame(ContentRemovalRequestStatus::Processed, $request->getStatus());
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
