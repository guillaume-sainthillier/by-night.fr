<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\CountryFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class CountryCrudControllerTest extends WebTestCase
{
    public function testTheCodeIsOnlySetOnCreation(): void
    {
        $client = $this->createAdminClient();
        CountryFactory::france()->create();

        $client->request('GET', '/_administration/country/FR/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="Country[id]"][disabled]');
    }

    public function testEditingSavesThePortalFields(): void
    {
        $client = $this->createAdminClient();
        CountryFactory::france()->create();

        $crawler = $client->request('GET', '/_administration/country/FR/edit');
        $form = $crawler->selectButton('Sauvegarder les modifications')->form([
            'Country[headline]' => 'Toutes les sorties en France',
            'Country[description]' => '<div>Concerts, <strong>expos</strong> et festivals.</div>',
            'Country[heroCaption]' => 'Place du Capitole, Toulouse',
            'Country[displayOrder]' => '2',
        ]);
        $featured = $form['Country[featured]'];
        self::assertInstanceOf(ChoiceFormField::class, $featured);
        $featured->tick();
        $client->submit($form);

        self::assertResponseRedirects();
        $country = CountryFactory::find(['id' => 'FR']);
        self::assertSame('Toutes les sorties en France', $country->getHeadline());
        self::assertSame('<div>Concerts, <strong>expos</strong> et festivals.</div>', $country->getDescription());
        self::assertSame('Place du Capitole, Toulouse', $country->getHeroCaption());
        self::assertTrue($country->isFeatured());
        self::assertSame(2, $country->getDisplayOrder());
    }

    public function testUploadingAHeroImageStoresIt(): void
    {
        $client = $this->createAdminClient();
        CountryFactory::france()->create();
        $path = tempnam(sys_get_temp_dir(), 'jpg');
        imagejpeg(imagecreatetruecolor(1920, 1080), $path);

        $crawler = $client->request('GET', '/_administration/country/FR/edit');
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $file = $form['Country[heroImageFile][file]'];
        self::assertInstanceOf(FileFormField::class, $file);
        $file->upload($path);
        $client->submit($form);

        // Country only computes the changes of what is persisted (DEFERRED_EXPLICIT) and the file
        // property is not mapped: the image is stored only because setHeroImageFile() bumps updatedAt
        self::assertResponseRedirects();
        $country = CountryFactory::find(['id' => 'FR']);
        self::assertTrue($country->hasHeroImage());
        self::assertSame([1920, 1080], $country->getHeroImage()->getDimensions());
        self::assertNotNull($country->getUpdatedAt());
    }

    public function testIndexCanListTheFeaturedCountriesOnly(): void
    {
        $client = $this->createAdminClient();
        CountryFactory::france()->create(['featured' => true]);
        CountryFactory::belgium()->create();

        $client->request('GET', '/_administration/country', ['filters' => ['featured' => '1']]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'France');
        self::assertSelectorTextNotContains('table', 'Belgique');
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
