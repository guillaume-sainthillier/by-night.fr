<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Enum\EventImportStatus;
use App\Factory\EventFactory;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

final class EventBulkTest extends AppWebTestCase
{
    public function testTheEventsPickedInTheListArePublishedButAnotherMembersAreLeftAlone(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $drafts = EventFactory::createMany(2, ['user' => $user, 'draft' => true]);
        $someoneElses = EventFactory::createOne(['draft' => true]);
        $client->loginUser($user);

        $form = $client->request('GET', self::organizerPath($user))->filter('#event-bulk-form')->form();
        $client->request('POST', self::organizerPath($user, 'evenements/lot'), [
            '_token' => $form['_token']->getValue(),
            '_target_path' => self::organizerPath($user) . '?status=hidden',
            'action' => 'publish',
            'ids' => [$drafts[0]->getId(), $drafts[1]->getId(), $someoneElses->getId()],
        ]);

        self::assertResponseRedirects(self::organizerPath($user) . '?status=hidden');
        self::assertSame(0, EventFactory::count(['user' => $user, 'draft' => true]));
        self::assertTrue(EventFactory::find(['id' => $someoneElses->getId()])->isDraft());
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 événements publiés');
    }

    public function testTheEventsPickedAreHiddenOrDeleted(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        [$kept, $deleted] = EventFactory::createMany(2, ['user' => $user, 'draft' => false]);
        $client->loginUser($user);
        $token = $client->request('GET', self::organizerPath($user))->filter('#event-bulk-form input[name="_token"]')->attr('value');
        $keptId = $kept->getId();
        $deletedId = $deleted->getId();

        $client->request('POST', self::organizerPath($user, 'evenements/lot'), ['_token' => $token, 'action' => 'draft', 'ids' => [$keptId]]);
        $client->request('POST', self::organizerPath($user, 'evenements/lot'), ['_token' => $token, 'action' => 'delete', 'ids' => [$deletedId]]);

        self::assertTrue(EventFactory::find(['id' => $keptId])->isDraft());
        self::assertSame(0, EventFactory::count(['id' => $deletedId]));
    }

    public function testNothingIsDoneWithoutTheTokenOfThePage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user, 'draft' => true]);
        $client->loginUser($user);

        $client->request('POST', self::organizerPath($user, 'evenements/lot'), ['_token' => 'forged', 'action' => 'publish', 'ids' => [$event->getId()]]);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue(EventFactory::find(['id' => $event->getId()])->isDraft());
    }

    public function testItGoesBackToThePersonalSpaceOnly(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);
        $token = $client->request('GET', self::organizerPath($user))->filter('#event-bulk-form input[name="_token"]')->attr('value');

        $client->request('POST', self::organizerPath($user, 'evenements/lot'), ['_token' => $token, '_target_path' => 'https://evil.example/espace-perso/', 'action' => 'publish', 'ids' => [1]]);
        self::assertResponseRedirects(self::organizerPath($user));

        $client->request('POST', self::organizerPath($user, 'evenements/lot'), ['_token' => $token, '_target_path' => '//evil.example/x', 'action' => 'publish', 'ids' => [1]]);
        self::assertResponseRedirects(self::organizerPath($user));
    }

    /**
     * The report's bar takes the events the import created: those it updated, or found unchanged, the member had before.
     */
    public function testTheReportOfAnImportPublishesTheEventsItCreated(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        [$created, $updated] = EventFactory::createMany(2, ['user' => $user, 'draft' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Completed, 'outcomes' => [
            ['row' => 2, 'reference' => 'A', 'id' => $created->getId(), 'name' => 'A', 'outcome' => 'created'],
            ['row' => 3, 'reference' => 'B', 'id' => $updated->getId(), 'name' => 'B', 'outcome' => 'updated'],
            ['row' => 4, 'reference' => 'C', 'id' => null, 'name' => 'C', 'outcome' => 'failed'],
        ]]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()));
        self::assertSelectorTextContains('#import-bulk-form', 'L’événement créé');
        $client->submit($crawler->filter('#import-bulk-form')->selectButton('Publier')->form());

        self::assertResponseRedirects(self::organizerPath($user, 'imports/' . $import->getId()));
        self::assertFalse(EventFactory::find(['id' => $created->getId()])->isDraft());
        self::assertTrue(EventFactory::find(['id' => $updated->getId()])->isDraft());
    }

    /**
     * An export imported again finds its events unchanged or updates them: deleting "the import's events" must not
     * delete them.
     */
    public function testTheReportOfAnImportDeletesTheEventsItCreatedOnly(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        [$created, $updated, $unchanged] = EventFactory::createMany(3, ['user' => $user]);
        $createdId = $created->getId();
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Completed, 'outcomes' => [
            ['row' => 2, 'reference' => 'A', 'id' => $createdId, 'name' => 'A', 'outcome' => 'created'],
            ['row' => 3, 'reference' => 'B', 'id' => $updated->getId(), 'name' => 'B', 'outcome' => 'updated'],
            ['row' => 4, 'reference' => 'C', 'id' => $unchanged->getId(), 'name' => 'C', 'outcome' => 'unchanged'],
        ]]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()));
        $client->submit($crawler->filter('#import-bulk-form')->selectButton('Supprimer')->form());

        self::assertSame(0, EventFactory::count(['id' => $createdId]));
        self::assertSame(2, EventFactory::count(['user' => $user]));
    }

    /**
     * The bulk forms ask before deleting through bulk-actions.ts, with their own attributes: form-confirm.ts, which
     * `data-confirm-message` calls, would ask on every button and send the form without the action clicked.
     */
    public function testTheBulkFormsAreNotConfirmedByTheGenericListener(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Completed, 'outcomes' => [
            ['row' => 2, 'reference' => 'A', 'id' => $event->getId(), 'name' => 'A', 'outcome' => 'created'],
        ]]);
        $client->loginUser($user);

        foreach ([self::organizerPath($user) => '#event-bulk-form', self::organizerPath($user, 'imports/' . $import->getId()) => '#import-bulk-form'] as $path => $form) {
            $bulkForm = $client->request('GET', $path)->filter($form);
            self::assertNotEmpty($bulkForm->attr('data-bulk-confirm-message'), $path);
            self::assertNull($bulkForm->attr('data-confirm-message'), $path);
        }
    }
}
