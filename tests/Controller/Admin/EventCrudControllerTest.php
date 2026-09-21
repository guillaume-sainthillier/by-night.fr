<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\EventFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EventCrudControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testEditFormExposesUserAndSystemImageUploads(): void
    {
        $client = $this->createAdminClient();
        $event = EventFactory::createOne();

        $client->request('GET', \sprintf('/_administration/event/%d/edit', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][accept="image/*"][name="Event[imageFile][file]"]');
        self::assertSelectorExists('input[type="file"][accept="image/*"][name="Event[imageSystemFile][file]"]');
        self::assertSelectorNotExists('[data-image-preview-current]');
        self::assertSelectorNotExists('input[name="Event[imageFile][delete]"]');
        self::assertSelectorNotExists('input[name="Event[imageSystemFile][delete]"]');
    }

    public function testEditFormSplitsSystemAndUserImagesInTwoFieldsets(): void
    {
        $client = $this->createAdminClient();
        $event = EventFactory::createOne();

        $crawler = $client->request('GET', \sprintf('/_administration/event/%d/edit', $event->getId()));

        self::assertResponseIsSuccessful();
        $inputsOf = static fn (string $fieldset): array => $crawler
            ->filterXPath(\sprintf('//fieldset[.//*[normalize-space(text())="%s"]]//input', $fieldset))
            ->each(static fn ($input): string => (string) $input->attr('name'));

        $systemInputs = $inputsOf('Image système');
        self::assertContains('Event[imageSystemFile][file]', $systemInputs);
        self::assertContains('Event[url]', $systemInputs);
        self::assertContains('Event[imageSystemHash]', $systemInputs);
        self::assertNotContains('Event[imageFile][file]', $systemInputs);

        $userInputs = $inputsOf('Image utilisateur');
        self::assertContains('Event[imageFile][file]', $userInputs);
        self::assertContains('Event[imageHash]', $userInputs);
        self::assertNotContains('Event[imageSystemFile][file]', $userInputs);
    }

    public function testEditFormPreviewsOnlyTheSystemImageOfAnImportedEvent(): void
    {
        $client = $this->createAdminClient();
        // Event::hasImage() is true here although the user image field is empty
        $event = EventFactory::createOne(['imageSystem' => self::embeddedFile('affiche.jpg')]);

        $crawler = $client->request('GET', \sprintf('/_administration/event/%d/edit', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-image-preview-current]'));
        self::assertSelectorExists('input[name="Event[imageSystemFile][delete]"][data-image-preview-delete]');
        self::assertSelectorNotExists('input[name="Event[imageFile][delete]"]');
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = static::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin->_real());

        return $client;
    }

    private static function embeddedFile(string $name): EmbeddedFile
    {
        $file = new EmbeddedFile();
        $file->setName($name);

        return $file;
    }
}
