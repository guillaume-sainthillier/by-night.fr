<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class UserCrudControllerTest extends WebTestCase
{
    public function testEditFormPreviewsOnlyTheUploadedProfilePicture(): void
    {
        $client = $this->createAdminClient();
        // User::hasImage() is true here although the system image field is empty
        $user = UserFactory::createOne(['image' => $this->embeddedFile('avatar.jpg')]);

        $crawler = $client->request('GET', \sprintf('/_administration/user/%d/edit', $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][accept="image/*"][name="User[imageFile][file]"]');
        self::assertSelectorExists('input[type="file"][accept="image/*"][name="User[imageSystemFile][file]"]');
        self::assertCount(1, $crawler->filter('[data-image-preview-current]'));
        self::assertSelectorExists('input[name="User[imageFile][delete]"][data-image-preview-delete]');
        self::assertSelectorNotExists('input[name="User[imageSystemFile][delete]"]');
    }

    public function testDetailShowsPlaceholderForMissingImages(): void
    {
        $client = $this->createAdminClient();
        $user = UserFactory::createOne();

        $crawler = $client->request('GET', \sprintf('/_administration/user/%d', $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.field-image .badge')->reduce(
            static fn ($badge): bool => 'Aucune image' === trim($badge->text()),
        ));
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
