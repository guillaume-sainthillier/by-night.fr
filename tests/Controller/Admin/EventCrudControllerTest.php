<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Admin\Filter\FromDataFilter;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class EventCrudControllerTest extends WebTestCase
{
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
        $event = EventFactory::createOne(['imageSystem' => $this->embeddedFile('affiche.jpg')]);

        $crawler = $client->request('GET', \sprintf('/_administration/event/%d/edit', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-image-preview-current]'));
        self::assertSelectorExists('input[name="Event[imageSystemFile][delete]"][data-image-preview-delete]');
        self::assertSelectorNotExists('input[name="Event[imageFile][delete]"]');
    }

    public function testSourceFilterKeepsOnlyEventsOfTheSelectedSource(): void
    {
        $client = $this->createAdminClient();
        $openAgenda = EventFactory::createOne(['fromData' => 'Open Agenda']);
        $facebook = EventFactory::createOne(['fromData' => 'Facebook']);
        $manual = EventFactory::createOne(['fromData' => null]);

        $client->request('GET', '/_administration/event', ['filters' => ['fromData' => 'Facebook']]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('tr[data-id="%d"]', $facebook->getId()));
        self::assertSelectorNotExists(\sprintf('tr[data-id="%d"]', $openAgenda->getId()));
        self::assertSelectorNotExists(\sprintf('tr[data-id="%d"]', $manual->getId()));
    }

    public function testSourceFilterWithoutSourceKeepsOnlyManualEvents(): void
    {
        $client = $this->createAdminClient();
        $imported = EventFactory::createOne(['fromData' => 'Open Agenda']);
        $manual = EventFactory::createOne(['fromData' => null]);

        $client->request('GET', '/_administration/event', ['filters' => ['fromData' => FromDataFilter::NO_SOURCE]]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('tr[data-id="%d"]', $manual->getId()));
        self::assertSelectorNotExists(\sprintf('tr[data-id="%d"]', $imported->getId()));
    }

    private function createAdminClient(): KernelBrowser
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin);

        return $client;
    }

    private function embeddedFile(string $name): EmbeddedFile
    {
        $file = new EmbeddedFile();
        $file->setName($name);

        return $file;
    }
}
