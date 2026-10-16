<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Enum\EventExportScope;
use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Factory\EventExportFactory;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Message\CheckEventImport;
use App\Message\RunEventExport;
use App\Message\RunEventImport;
use App\Tests\AppWebTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\refresh;

final class TransferControllerTest extends AppWebTestCase
{
    public function testThePageSubscribesTheMemberToTheirChannel(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/import-export');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/espace-perso/imports"] input[type="file"]');
        self::assertSelectorExists('form[action="/espace-perso/exports"]');
        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertSame('__Secure-mercure_access_token', $cookie?->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertStringContainsString(
            rawurlencode('https://by-night.test/users/' . $user->getId()),
            (string) $crawler->filter('[data-page]')->attr('data-page-params'),
        );
    }

    public function testAVisitorIsAskedToLogIn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/espace-perso/import-export');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheTemplateIsDownloaded(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/espace-perso/import-export/modele.csv');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=modele-import-by-night.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnUploadedFileIsStoredThenChecked(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $client->submit($client->request('GET', '/espace-perso/import-export')->filter('form[action="/espace-perso/imports"]')->form(), [
            'event_import[file]' => $this->upload("Nom;Date de début\nConcert;12/03/2027\n", 'mes-evenements.csv'),
        ]);

        $import = EventImportFactory::find(['user' => $user]);
        self::assertResponseRedirects('/espace-perso/imports/' . $import->getId());
        self::assertSame(['mes-evenements.csv', TransferFormat::Csv, EventImportStatus::Uploaded], [$import->getOriginalName(), $import->getFormat(), $import->getStatus()]);
        self::assertStringContainsString('Concert', $this->storage()->read((string) $import->getFilePath()));
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new CheckEventImport((int) $import->getId()), $sent[0]->getMessage());
    }

    public function testAnotherFormatIsTurnedDown(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $client->submit($client->request('GET', '/espace-perso/import-export')->filter('form[action="/espace-perso/imports"]')->form(), [
            'event_import[file]' => $this->upload('%PDF-1.4', 'affiche.pdf'),
        ]);

        self::assertResponseRedirects('/espace-perso/import-export');
        self::assertSame(0, EventImportFactory::count(['user' => $user]));
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Importez un fichier CSV ou Excel (XLSX).');
    }

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

    public function testTheCheckedEventsAreImportedOnceWhateverTheClicks(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'validCount' => 3]);
        $client->loginUser($user);
        // One container for both clicks (the client still empties the transport between requests)
        $client->disableReboot();
        $form = $client->request('GET', '/espace-perso/imports/' . $import->getId())->selectButton('Importer les 3 événements valides')->form();

        $client->submit($form);
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new RunEventImport((int) $import->getId()), $sent[0]->getMessage());

        $client->submit($form);
        self::assertCount(0, $this->transport()->getSent(), 'The second click starts nothing');
        refresh($import);
        self::assertSame(EventImportStatus::Importing, $import->getStatus());
    }

    public function testAnImportIsNotStartedWithoutTheTokenOfItsPage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'validCount' => 3]);
        $client->loginUser($user);

        $client->request('POST', '/espace-perso/imports/' . $import->getId() . '/importer', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        refresh($import);
        self::assertSame(EventImportStatus::Checked, $import->getStatus());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOtherMembersPages(): iterable
    {
        yield 'report' => ['/espace-perso/imports/%d'];
        yield 'status' => ['/espace-perso/imports/%d/statut'];
        yield 'file' => ['/espace-perso/imports/%d/fichiers/original'];
    }

    #[DataProvider('provideOtherMembersPages')]
    public function testAnotherMembersImportIsOutOfReach(string $url): void
    {
        $client = self::createClient();
        $import = EventImportFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', \sprintf($url, $import->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheFilesOfAnImportAreDownloadedByItsMember(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $import = EventImportFactory::createOne(['user' => $user, 'originalName' => 'saison.csv', 'status' => EventImportStatus::Checked, 'errorFilePath' => 'imports/x/erreurs.csv']);
        $this->storage()->write('imports/x/erreurs.csv', "Nom;Erreurs\nConcert;Date de début : la date de début manque.\n");
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/imports/' . $import->getId() . '/fichiers/erreurs');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('saison-erreurs.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('la date de début manque', (string) $client->getInternalResponse()->getContent());
    }

    /**
     * @return iterable<string, array{EventImportStatus, string}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'checking' => [EventImportStatus::Checking, 'Annuler la vérification'];
        yield 'check failed' => [EventImportStatus::CheckFailed, 'Le fichier n’a pas pu être vérifié'];
        yield 'checked' => [EventImportStatus::Checked, 'Abandonner cet import'];
        yield 'importing' => [EventImportStatus::Importing, 'l’import continue'];
        yield 'failed' => [EventImportStatus::Failed, 'Reprendre l’import'];
        yield 'cancelled' => [EventImportStatus::Cancelled, 'Cet import a été annulé'];
    }

    #[DataProvider('provideStatuses')]
    public function testTheReportSaysWhereTheImportStands(EventImportStatus $status, string $expected): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $import = EventImportFactory::createOne(['user' => $user, 'status' => $status, 'eventCount' => 4, 'processedCount' => 2]);
        $client->loginUser($user);

        $client->request('GET', '/espace-perso/imports/' . $import->getId() . '/statut');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected, (string) $client->getInternalResponse()->getContent());
    }

    /**
     * A file the browser sends under its name (its extension is what the form checks first).
     */
    private function upload(string $content, string $name): string
    {
        $directory = sys_get_temp_dir() . '/transfer-upload-' . bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($path = $directory . '/' . $name, $content);

        return $path;
    }

    private function transport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport in memory in tests (messenger.yaml), AMQP in the container PHPStan reads */
        $transport = self::getContainer()->get('messenger.transport.transfers');

        return $transport;
    }

    private function storage(): FilesystemOperator
    {
        return self::getContainer()->get('transfers.storage');
    }
}
