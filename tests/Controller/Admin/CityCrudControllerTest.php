<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\CityFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class CityCrudControllerTest extends WebTestCase
{
    public function testEditingSavesThePortalFields(): void
    {
        $client = $this->createAdminClient();
        $city = CityFactory::toulouse()->create();

        $crawler = $client->request('GET', \sprintf('/_administration/city/%d/edit', $city->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form([
            'City[headline]' => 'La ville rose by night',
            'City[description]' => '<div>Du Bikini au Zénith.</div>',
            'City[displayOrder]' => '1',
        ]);
        $metropolis = $form['City[metropolis]'];
        self::assertInstanceOf(ChoiceFormField::class, $metropolis);
        $metropolis->tick();
        $client->submit($form);

        self::assertResponseRedirects();
        $city = CityFactory::find(['id' => $city->getId()]);
        self::assertSame('La ville rose by night', $city->getHeadline());
        self::assertSame('<div>Du Bikini au Zénith.</div>', $city->getDescription());
        self::assertTrue($city->isMetropolis());
        self::assertSame(1, $city->getDisplayOrder());
    }

    public function testUploadingAHeroImageStoresIt(): void
    {
        $client = $this->createAdminClient();
        $city = CityFactory::toulouse()->create();
        $path = tempnam(sys_get_temp_dir(), 'jpg');
        imagejpeg(imagecreatetruecolor(1920, 1080), $path);

        $crawler = $client->request('GET', \sprintf('/_administration/city/%d/edit', $city->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $file = $form['City[heroImageFile][file]'];
        self::assertInstanceOf(FileFormField::class, $file);
        $file->upload($path);
        $client->submit($form);

        self::assertResponseRedirects();
        $city = CityFactory::find(['id' => $city->getId()]);
        self::assertTrue($city->hasHeroImage());
        self::assertNotNull($city->getUpdatedAt());
    }

    public function testIndexCanListTheMetropolisesOnly(): void
    {
        $client = $this->createAdminClient();
        CityFactory::toulouse()->create(['metropolis' => true]);
        CityFactory::createOne(['name' => 'Castelnaudary']);

        $client->request('GET', '/_administration/city', ['filters' => ['metropolis' => '1']]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Toulouse');
        self::assertSelectorTextNotContains('table', 'Castelnaudary');
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
