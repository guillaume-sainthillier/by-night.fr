<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace\Transfer;

use App\Enum\EventExportScope;
use App\Enum\EventExportStatus;
use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Factory\EventExportFactory;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Message\RunEventExport;
use App\Tests\AppWebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ExportControllerTest extends AppWebTestCase
{
    public function testAMemberRunsOneJobAtATime(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checking]);
        $client->loginUser($user);

        $client->submit($client->request('GET', '/espace-perso/import-export')->filter('form[action="/espace-perso/exports"]')->form());

        self::assertSame(0, EventExportFactory::count(['user' => $user]));
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'déjà en cours');
    }

    public function testAnExportIsStartedWithItsScopeAndFormat(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->submit($client->request('GET', '/espace-perso/import-export')->filter('form[action="/espace-perso/exports"]')->form(), [
            'event_export[scope]' => 'upcoming',
            'event_export[format]' => 'ics',
        ]);

        $export = EventExportFactory::find(['user' => $user]);
        self::assertSame([EventExportScope::Upcoming, TransferFormat::Ics], [$export->getScope(), $export->getFormat()]);
        self::assertEquals(new RunEventExport((int) $export->getId()), $this->transport()->getSent()[0]->getMessage());
    }

    private function transport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport in memory in tests (messenger.yaml), AMQP in the container PHPStan reads */
        $transport = self::getContainer()->get('messenger.transport.transfers');

        return $transport;
    }

    public function testAnotherMembersExportIsOutOfReach(): void
    {
        $client = self::createClient();
        $export = EventExportFactory::createOne(['status' => EventExportStatus::Completed, 'filePath' => 'exports/x/evenements.csv']);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/espace-perso/exports/' . $export->getId() . '/fichier');

        self::assertResponseStatusCodeSame(403);
    }
}
