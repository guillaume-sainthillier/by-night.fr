<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\TagFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the Gedmo slug regeneration on a TextField, which is how every slug but Page's is exposed
 * in the back-office. Page uses SlugField instead, so PageCrudControllerTest covers the other half.
 */
final class TagCrudControllerTest extends WebTestCase
{
    public function testCreateTagGeneratesSlugFromNameWhenSlugIsLeftEmpty(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/_administration/tag/new');
        $client->submitForm('Créer', [
            'Tag[name]' => 'Musique Électronique',
            'Tag[slug]' => '',
        ]);

        self::assertResponseRedirects();
        $tag = TagFactory::find(['name' => 'Musique Électronique']);
        self::assertSame('musique-electronique', $tag->getSlug());
    }

    public function testExplicitSlugIsKept(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/_administration/tag/new');
        $client->submitForm('Créer', [
            'Tag[name]' => 'Musique Électronique',
            'Tag[slug]' => 'electro',
        ]);

        self::assertResponseRedirects();
        $tag = TagFactory::find(['name' => 'Musique Électronique']);
        self::assertSame('electro', $tag->getSlug());
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
