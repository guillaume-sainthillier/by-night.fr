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
use App\Factory\EventTimesheetFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class EventTimesheetCrudControllerTest extends AppWebTestCase
{
    public function testIndexListsTimesheets(): void
    {
        $client = $this->createAdminClient();
        $event = EventFactory::createOne(['name' => 'Concert']);
        EventTimesheetFactory::createOne(['event' => $event, 'hours' => '20h-23h']);

        $client->request('GET', '/_administration/event-timesheet');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Concert');
    }

    public function testDetailShowsTimesheet(): void
    {
        $client = $this->createAdminClient();
        $timesheet = EventTimesheetFactory::createOne(['hours' => '19h-22h']);

        $client->request('GET', \sprintf('/_administration/event-timesheet/%d', $timesheet->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '19h-22h');
    }

    public function testEditingATimesheetWorks(): void
    {
        $client = $this->createAdminClient();
        $timesheet = EventTimesheetFactory::createOne(['hours' => 'Ancien horaire']);

        $crawler = $client->request('GET', \sprintf('/_administration/event-timesheet/%d/edit', $timesheet->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, [
            'EventTimesheet[hours]' => 'Nouvel horaire',
        ]);

        self::assertResponseRedirects();
        $timesheet = EventTimesheetFactory::find(['id' => $timesheet->getId()]);
        self::assertSame('Nouvel horaire', $timesheet->getHours());
    }

    public function testDeletingATimesheetWorks(): void
    {
        $client = $this->createAdminClient();
        $timesheet = EventTimesheetFactory::createOne();
        $timesheetId = $timesheet->getId();

        $crawler = $client->request('GET', \sprintf('/_administration/event-timesheet/%d', $timesheetId));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', \sprintf('/_administration/event-timesheet/%d/delete', $timesheetId), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertSame(0, EventTimesheetFactory::count(['id' => $timesheetId]));
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        return $client;
    }
}
