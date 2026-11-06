<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PublisherCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsPublishers(): void
    {
        $client = $this->createAdminClient();
        PublisherFactory::createOne(['name' => 'Salle de Concert']);
        PublisherFactory::createOne(['name' => 'Collectif Artistique']);

        $client->request('GET', '/_administration/publisher');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Salle de Concert');
        self::assertSelectorTextContains('table', 'Collectif Artistique');
    }

    public function testDetailShowsPublisher(): void
    {
        $client = $this->createAdminClient();
        $publisher = PublisherFactory::createOne(['name' => 'Mon Organisateur', 'description' => 'Une belle présentation']);

        $client->request('GET', \sprintf('/_administration/publisher/%d', $publisher->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mon Organisateur');
        self::assertSelectorTextContains('body', 'Une belle présentation');
    }

    public function testEditingAPublisherWorks(): void
    {
        $client = $this->createAdminClient();
        $publisher = PublisherFactory::createOne(['name' => 'Ancien nom', 'website' => 'https://old.test']);

        $crawler = $client->request('GET', \sprintf('/_administration/publisher/%d/edit', $publisher->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, [
            'Publisher[name]' => 'Nouveau nom',
            'Publisher[website]' => 'https://new.test',
        ]);

        self::assertResponseRedirects();
        $publisher = PublisherFactory::find(['id' => $publisher->getId()]);
        self::assertSame('Nouveau nom', $publisher->getName());
        self::assertSame('https://new.test', $publisher->getWebsite());
    }

    public function testDeleteIsDisabled(): void
    {
        $client = $this->createAdminClient();
        $publisher = PublisherFactory::createOne();

        $client->request('GET', \sprintf('/_administration/publisher/%d', $publisher->getId()));

        // DELETE action is disabled on this controller
        self::assertSelectorNotExists('a[name="delete"]');
        self::assertSelectorNotExists('button[name="delete"]');
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
