<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Factory\PageFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PageControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testShowRendersContentAndSeoMetadata(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = static::createClient();
        PageFactory::createOne([
            'title' => 'Comment publier un événement',
            'content' => '<h2>Étape 1</h2><p>Créez un <strong>compte</strong>.</p>',
            'metaTitle' => 'Publier un événement sur By Night',
            'metaDescription' => 'Guide pas à pas pour publier votre événement.',
        ]);

        $client->request('GET', '/p/comment-publier-un-evenement');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Publier un événement sur By Night - By Night');
        self::assertSelectorTextContains('h1', 'Comment publier un événement');
        self::assertSelectorExists('meta[name="description"][content="Guide pas à pas pour publier votre événement."]');
        self::assertSelectorTextContains('.page-content h2', 'Étape 1');
        self::assertSelectorTextContains('.page-content strong', 'compte');
    }

    public function testShowFallsBackToTitleWhenMetaTitleIsEmpty(): void
    {
        $client = static::createClient();
        PageFactory::createOne([
            'title' => 'Mentions légales',
            'metaTitle' => null,
        ]);

        $client->request('GET', '/p/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Mentions légales - By Night');
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/p/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }

    public function testSlugIsGeneratedFromTitleAndKeptOnRename(): void
    {
        $page = PageFactory::createOne(['title' => 'Mentions légales 2026']);
        self::assertSame('mentions-legales-2026', $page->getSlug());

        // updatable: false — renaming must not change the public URL
        $page->setTitle('Mentions légales 2027');
        $page->_save();
        self::assertSame('mentions-legales-2026', $page->getSlug());
    }
}
