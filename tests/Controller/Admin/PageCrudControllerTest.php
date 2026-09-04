<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\PageFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PageCrudControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testIndexListsPagesWithViewAction(): void
    {
        $client = $this->createAdminClient();
        PageFactory::createOne(['title' => 'Qui sommes-nous']);

        $client->request('GET', '/_administration/page');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Qui sommes-nous');
        self::assertSelectorExists('a[href="/p/qui-sommes-nous"][target="_blank"]');
    }

    public function testNewFormExposesCodeEditorAndVichImageWidget(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/_administration/page/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.field-code_editor textarea[name="Page[content]"]');
        self::assertSelectorExists('input[type="file"][name="Page[imageFile][file]"]');
        self::assertSelectorExists('input[name="Page[slug]"]:not([required])');
    }

    public function testCreatePageGeneratesSlugFromTitle(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/_administration/page/new');
        $client->submitForm('Créer', [
            'Page[title]' => 'Mentions légales',
            'Page[content]' => '<p>Éditeur <em>By Night</em></p>',
            'Page[metaTitle]' => 'Mentions légales de By Night',
            'Page[metaDescription]' => 'Informations légales.',
        ]);

        self::assertResponseRedirects();
        $page = PageFactory::find(['title' => 'Mentions légales']);
        self::assertSame('mentions-legales', $page->getSlug());
        self::assertSame('<p>Éditeur <em>By Night</em></p>', $page->getContent());
        self::assertSame('Mentions légales de By Night', $page->getMetaTitle());
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_administration/page');

        self::assertResponseRedirects();
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = static::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin->_real());

        return $client;
    }
}
