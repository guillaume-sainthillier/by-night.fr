<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\CommentFactory;
use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

use function Zenstruck\Foundry\Persistence\refresh;

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

    public function testSavingAUserMarksThemVerified(): void
    {
        $client = $this->createAdminClient();
        $user = UserFactory::createOne(['verified' => false]);

        $crawler = $client->request('GET', \sprintf('/_administration/user/%d/edit', $user->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $verified = $form['User[verified]'];
        self::assertInstanceOf(ChoiceFormField::class, $verified);
        $verified->tick();
        $client->submit($form);

        self::assertResponseRedirects();
        refresh($user);
        self::assertTrue($user->isVerified());
    }

    /**
     * As from the profile: comments and favourites go with the member, their events stay online
     * (BY-NIGHTFR-66X: the foreign keys refused a plain remove).
     */
    public function testDeletingAMemberKeepsTheirEventsOnline(): void
    {
        $client = $this->createAdminClient();
        $user = UserFactory::createOne();
        $userId = $user->getId();
        $event = EventFactory::createOne(['user' => $user]);
        $favourite = EventFactory::createOne(['participations' => 3]);
        UserEventFactory::createOne(['user' => $user, 'event' => $favourite, 'going' => true]);
        CommentFactory::createOne(['user' => $user, 'event' => $favourite]);

        $crawler = $client->request('GET', \sprintf('/_administration/user/%d', $userId));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', \sprintf('/_administration/user/%d/delete', $userId), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertSame(0, UserFactory::count(['id' => $userId]));
        self::assertSame(0, CommentFactory::count());
        self::assertSame(0, UserEventFactory::count());
        self::assertNull(EventFactory::find(['id' => $event->getId()])->getUser());
        self::assertSame(2, EventFactory::find(['id' => $favourite->getId()])->getParticipations());
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
