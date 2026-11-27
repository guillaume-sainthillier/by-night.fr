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
use App\Tests\AppWebTestCase;

use function Zenstruck\Foundry\Persistence\save;

final class PageControllerTest extends AppWebTestCase
{
    public function testShowRendersContentAndSeoMetadata(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = self::createClient();
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
        $client = self::createClient();
        PageFactory::createOne([
            'title' => 'Mentions légales',
            'metaTitle' => null,
        ]);

        $client->request('GET', '/p/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Mentions légales - By Night');
    }

    public function testShowAnswersMarkdownAtItsMdUrl(): void
    {
        $client = self::createClient();
        PageFactory::createOne([
            'title' => 'Q&A : publier un événement',
            'content' => '<h2>Étape 1</h2><p>Créez un <strong>compte</strong> sur <a href="https://by-night.test/inscription">By Night</a>.</p><table><thead><tr><th>Lieu</th><th>Prix</th></tr></thead><tbody><tr><td>Bikini</td><td>Gratuit</td></tr></tbody></table>',
        ]);

        $client->request('GET', '/p/q-a-publier-un-evenement.md');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        // Nothing escaped, the tables kept
        self::assertSame("# Q&A : publier un événement\n\n## Étape 1\n\nCréez un **compte** sur [By Night](https://by-night.test/inscription).\n\n| Lieu | Prix |\n|---|---|\n| Bikini | Gratuit |\n", $client->getInternalResponse()->getContent());
    }

    public function testShowAnswersMarkdownToAnAgentAskingForIt(): void
    {
        $client = self::createClient();
        PageFactory::createOne(['title' => 'Mentions légales', 'content' => '<p>Le contenu.</p>']);

        $client->request('GET', '/p/mentions-legales', server: ['HTTP_ACCEPT' => 'text/markdown, text/html;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertResponseHeaderSame('Vary', 'Accept');
        self::assertStringStartsWith("# Mentions légales\n\nLe contenu.", $client->getInternalResponse()->getContent());
    }

    public function testTheHtmlPageLinksToItsMarkdownVersion(): void
    {
        $client = self::createClient();
        PageFactory::createOne(['title' => 'Mentions légales']);

        // A browser asks for text/html first
        $client->request('GET', '/p/mentions-legales', server: ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $links = preg_split('/,\s*(?=<)/', (string) $client->getResponse()->headers->get('Link'));
        self::assertContains('<https://by-night.test/p/mentions-legales.md>; rel="alternate"; type="text/markdown"', $links);
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/p/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }

    public function testSlugIsGeneratedFromTitleAndKeptOnRename(): void
    {
        $page = PageFactory::createOne(['title' => 'Mentions légales 2026']);
        self::assertSame('mentions-legales-2026', $page->getSlug());

        // updatable: false — renaming must not change the public URL
        $page->setTitle('Mentions légales 2027');
        save($page);
        /* @phpstan-ignore-next-line */
        self::assertSame('mentions-legales-2026', $page->getSlug());
    }
}
