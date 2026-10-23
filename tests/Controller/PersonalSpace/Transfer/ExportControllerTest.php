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
use App\Enum\PersonalEventFilter;
use App\Enum\TransferFormat;
use App\Factory\EventExportFactory;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Message\RunEventExport;
use App\Tests\AppWebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ExportControllerTest extends AppWebTestCase
{
    public function testThePageSubscribesTheMemberToTheirChannel(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'exports'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/exports"] select[name="event_export[format]"]');
        self::assertSelectorExists('nav a.active[href$="/exports"]');
        self::assertSame('__Secure-mercure_access_token', ($client->getResponse()->headers->getCookies()[0] ?? null)?->getName());
        self::assertStringContainsString(self::organizerPath($user, 'exports/historique'), (string) $crawler->filter('[data-page]')->attr('data-page-params'));
    }

    public function testTheHistoryListsTheMembersExportsOnly(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        EventExportFactory::createOne(['user' => $user, 'scope' => EventExportScope::Drafts]);
        EventExportFactory::createOne(['scope' => EventExportScope::Past]);
        $client->loginUser($user);

        $client->request('GET', self::organizerPath($user, 'exports/historique'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.list-group', 'Brouillons');
        self::assertSelectorTextNotContains('.list-group', 'Passés');
    }

    public function testAnExportStartedFromTheListKeepsItsSearchAndStatus(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'exports'), ['q' => 'jazz', 'status' => 'hidden']);
        self::assertSelectorTextContains('#export-list-filter', 'jazz');
        self::assertSelectorTextContains('#export-list-filter', 'masqués');
        $client->submit($crawler->filter('form[action$="/exports"]')->form());

        $export = EventExportFactory::find(['user' => $user]);
        self::assertSame('jazz', $export->getSearchQuery());
        self::assertSame(PersonalEventFilter::Hidden, $export->getStatusFilter());
    }

    public function testAMemberRunsOneJobAtATime(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checking]);
        $client->loginUser($user);

        $client->submit($client->request('GET', self::organizerPath($user, 'exports'))->filter('form[action$="/exports"]')->form());

        self::assertSame(0, EventExportFactory::count(['user' => $user]));
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'déjà en cours');
    }

    public function testAnExportIsStartedWithItsScopeAndFormat(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->submit($client->request('GET', self::organizerPath($user, 'exports'))->filter('form[action$="/exports"]')->form(), [
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
        $member = UserFactory::createOne();
        $client->loginUser($member);

        $client->request('GET', self::organizerPath($member, 'exports/' . $export->getId() . '/fichier'));

        self::assertResponseStatusCodeSame(403);
    }
}
