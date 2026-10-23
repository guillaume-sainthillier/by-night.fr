<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace\Transfer;

use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Factory\EventImportFactory;
use App\Factory\UserFactory;
use App\Message\CheckEventImport;
use App\Message\RunEventImport;
use App\Tests\AppWebTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\refresh;

final class ImportControllerTest extends AppWebTestCase
{
    public function testThePageSubscribesTheMemberToTheirChannel(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'imports'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/imports"] input[type="file"]');
        self::assertSelectorExists('form[action$="/imports"] .dropzone input[type="file"][accept=".csv,.xlsx"][data-max-size="2000000"]');
        self::assertSelectorExists('nav a.active[href$="/imports"]');
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
        $client->request('GET', '/espace-perso/imports');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheReportTakesTheCorrectedFile(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'errorFilePath' => 'imports/x/erreurs.csv', 'invalidCount' => 2]);
        $client->loginUser($user);

        $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()));

        self::assertSelectorTextContains('.card-title', 'Importer le fichier corrigé');
        self::assertSelectorExists('form[action$="/imports"] .dropzone input[type="file"]');
    }

    public function testTheTemplateIsDownloaded(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        $client->loginUser($member);

        $client->request('GET', self::organizerPath($member, 'imports/modele.csv'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=modele-import-by-night.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnUploadedFileIsStoredThenChecked(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $client->loginUser($user);

        $client->submit($client->request('GET', self::organizerPath($user, 'imports'))->filter('form[action$="/imports"]')->form(), [
            'event_import[file]' => $this->upload("Nom;Date de début\nConcert;12/03/2027\n", 'mes-evenements.csv'),
        ]);

        $import = EventImportFactory::find(['user' => $user]);
        self::assertResponseRedirects(self::organizerPath($user, 'imports/' . $import->getId()));
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

        $client->submit($client->request('GET', self::organizerPath($user, 'imports'))->filter('form[action$="/imports"]')->form(), [
            'event_import[file]' => $this->upload('%PDF-1.4', 'affiche.pdf'),
        ]);

        self::assertResponseRedirects(self::organizerPath($user, 'imports'));
        self::assertSame(0, EventImportFactory::count(['user' => $user]));
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Importez un fichier CSV ou Excel (XLSX).');
    }

    public function testTheCheckedEventsAreImportedOnceWhateverTheClicks(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'validCount' => 3]);
        $client->loginUser($user);
        // One container for both clicks (the client still empties the transport between requests)
        $client->disableReboot();
        $form = $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()))->selectButton('Importer les 3 événements valides')->form();

        $client->submit($form);
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new RunEventImport((int) $import->getId()), $sent[0]->getMessage());

        $client->submit($form);
        self::assertCount(0, $this->transport()->getSent(), 'The second click starts nothing');
        refresh($import);
        self::assertSame(EventImportStatus::Importing, $import->getStatus());
    }

    public function testTheWarnedEventsUntickedAreLeftOut(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'validCount' => 3, 'warningCount' => 1,
            'warnings' => [['row' => 4, 'reference' => 'JAZZ', 'column' => 'Nom', 'message' => 'Ressemble à un événement en ligne']]]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()));
        $form = $crawler->filter('#import-run-form')->form();
        $form['skip'][0]->tick();
        $client->submit($form);

        $saved = EventImportFactory::find(['id' => $import->getId()]);
        self::assertSame([4], $saved->getSkippedRows());
        self::assertSame(EventImportStatus::Importing, $saved->getStatus());
    }

    public function testTheReportListsTheEventsNotImportedFirstAndFoldsTheRest(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $outcomes = array_map(static fn (int $row): array => ['row' => $row, 'reference' => 'R' . $row, 'id' => null, 'name' => 'Événement ' . $row, 'outcome' => 30 === $row ? 'failed' : 'unchanged'], range(2, 31));
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Completed, 'outcomes' => $outcomes]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId()));

        self::assertSame('Événement 30', trim($crawler->filter('table tbody:not(.collapse) tr')->first()->filter('td')->eq(1)->text()));
        self::assertCount(20, $crawler->filter('table tbody:not(.collapse) tr'));
        self::assertCount(10, $crawler->filter('#import-other-outcomes tr'));
        self::assertSelectorTextContains('[data-bs-target="#import-other-outcomes"]', 'Voir les 10 autres événements');
    }

    public function testAnImportIsNotStartedWithoutTheTokenOfItsPage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        $import = EventImportFactory::createOne(['user' => $user, 'status' => EventImportStatus::Checked, 'validCount' => 3]);
        $client->loginUser($user);

        $client->request('POST', self::organizerPath($user, 'imports/' . $import->getId() . '/importer'), ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        refresh($import);
        self::assertSame(EventImportStatus::Checked, $import->getStatus());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOtherMembersPages(): iterable
    {
        yield 'report' => ['imports/%d'];
        yield 'status' => ['imports/%d/statut'];
        yield 'file' => ['imports/%d/fichiers/original'];
    }

    #[DataProvider('provideOtherMembersPages')]
    public function testAnotherMembersImportIsOutOfReach(string $url): void
    {
        $client = self::createClient();
        $import = EventImportFactory::createOne();
        $member = UserFactory::createOne();
        $client->loginUser($member);

        // At its own URL: the other member does not manage its publisher
        $client->request('GET', self::organizerPath($import->getUser(), \sprintf($url, $import->getId())));
        self::assertResponseStatusCodeSame(403);

        // Through the member's own publisher: theirs is not
        $client->request('GET', self::organizerPath($member, \sprintf($url, $import->getId())));
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheFilesOfAnImportAreDownloadedByItsMember(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $import = EventImportFactory::createOne(['user' => $user, 'originalName' => 'saison.csv', 'status' => EventImportStatus::Checked, 'errorFilePath' => 'imports/x/erreurs.csv']);
        $this->storage()->write('imports/x/erreurs.csv', "Nom;Erreurs\nConcert;Date de début : la date de début manque.\n");
        $client->loginUser($user);

        $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId() . '/fichiers/erreurs'));

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

        $client->request('GET', self::organizerPath($user, 'imports/' . $import->getId() . '/statut'));

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
