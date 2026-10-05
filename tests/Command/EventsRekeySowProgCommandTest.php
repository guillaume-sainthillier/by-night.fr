<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Factory\EventFactory;
use App\Factory\ParserDataFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * A SowProg event stored under its v1.2 programme id takes the API v2 id of its next date.
 */
final class EventsRekeySowProgCommandTest extends AppKernelTestCase
{
    private string $csv;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->csv = (string) tempnam(sys_get_temp_dir(), 'sowprog');
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function testAProgrammeTakesTheIdOfItsNextServedDate(): void
    {
        $event = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687881']);
        $past = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687151']);
        $other = EventFactory::createOne(['externalOrigin' => 'openagenda', 'externalId' => '687871']);
        ParserDataFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687881']);

        $this->mapping([
            [5, 687881, '-2 days', 'PUBLISHED'],
            [8, 687881, '+9 days', 'PUBLISHED'],
            [6, 687881, '+3 days', 'DRAFT'],
            [7, 687881, '+5 days', 'CANCELLED'],
            [3, 687151, '-30 days', 'PUBLISHED'],
            [4, 687151, '-20 days', 'PUBLISHED'],
            [9, 687871, '+5 days', 'PUBLISHED'],
        ]);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame('7', EventFactory::find(['id' => $event->getId()])->getExternalId(), 'The next date the API serves, drafts left out');
        self::assertSame('4', EventFactory::find(['id' => $past->getId()])->getExternalId(), 'A show over takes its last date');
        self::assertSame('687871', EventFactory::find(['id' => $other->getId()])->getExternalId(), 'Another source is left alone');
        self::assertSame(0, ParserDataFactory::count(['externalOrigin' => 'sowprog']), 'The content hashes of the old ids are dropped');
    }

    public function testPreviewWritesNothing(): void
    {
        $event = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687881']);
        $this->mapping([[5, 687881, '+2 days', 'PUBLISHED']]);

        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('1 event(s) would be re-keyed', $display);
        self::assertSame('687881', EventFactory::find(['id' => $event->getId()])->getExternalId());
    }

    public function testAPerDateRowTakesTheIdOfItsDate(): void
    {
        // The scheme until 2026-01: "<programme>-<date id>"; the programme row takes another date
        $perDate = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '771861-1000008']);
        $programme = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '771861']);
        $this->mapping([
            [8, 771861, '+3 days', 'PUBLISHED'],
            [9, 771861, '+10 days', 'PUBLISHED'],
        ]);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame('8', EventFactory::find(['id' => $perDate->getId()])->getExternalId());
        self::assertSame('9', EventFactory::find(['id' => $programme->getId()])->getExternalId(), 'The date the per-date row took is no longer free');
    }

    public function testAnUpcomingRowTheCsvDoesNotKnowIsFoundInTheLiveFeed(): void
    {
        $day = new DateTimeImmutable('+5 days');
        $unknown = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '798101', 'name' => 'New York Swing 4tet', 'startDate' => $day, 'endDate' => $day]);
        $namesake = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '798102', 'name' => 'Jam session', 'startDate' => $day, 'endDate' => $day]);
        $this->mapping([]);
        self::getContainer()->set('sowprog.client', new MockHttpClient(new JsonMockResponse([
            'data' => [
                ['id' => 8099, 'title' => 'New York Swing 4tet', 'dates' => [['date' => $day->format('Y-m-d') . 'T00:00:00.000Z']]],
                ['id' => 8100, 'title' => 'Jam session', 'dates' => [['date' => $day->modify('+7 days')->format('Y-m-d') . 'T00:00:00.000Z']]],
            ],
            'meta' => ['total' => 2, 'page' => 1, 'pageSize' => 200, 'totalPages' => 1],
        ]), 'https://app.sowprog.com/api/v2/'));

        $this->doRunCommand(['--apply' => true]);

        self::assertSame('8099', EventFactory::find(['id' => $unknown->getId()])->getExternalId());
        self::assertSame('798102', EventFactory::find(['id' => $namesake->getId()])->getExternalId(), 'The same title on another day is another show');
    }

    public function testAnIdAlreadyStoredIsLeftToItsRow(): void
    {
        $event = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687881', 'endDate' => new DateTimeImmutable('-1 day')]);
        EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '5']);
        $other = EventFactory::createOne(['externalOrigin' => 'sowprog', 'externalId' => '687151']);
        $this->mapping([[5, 687881, '+2 days', 'PUBLISHED'], [6, 687151, '+2 days', 'PUBLISHED']]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        self::assertSame('687881', EventFactory::find(['id' => $event->getId()])->getExternalId());
        self::assertSame('6', EventFactory::find(['id' => $other->getId()])->getExternalId(), 'The others are re-keyed');
        self::assertMatchesRegularExpression('/already stored\s+1/', $display);
    }

    /**
     * @param list<array{int, int, string, string}> $rows ID_V2, ID_LEGACY_PROGRAMME, relative date, STATUT
     */
    private function mapping(array $rows): void
    {
        $lines = ['ID_V2,ID_LEGACY_PROGRAMME,ID_LEGACY_EVENEMENT,ID_LEGACY_DATE,DATE,STATUT,TITRE'];
        foreach ($rows as [$v2, $programme, $date, $status]) {
            $lines[] = \sprintf('%d,%d,%d,%d,%s,%s,"Les trois soeurs, d\'Anton Tchekhov"', $v2, $programme, $programme + 50, $v2 + 1_000_000, new DateTimeImmutable($date)->format('Y-m-d'), $status);
        }

        file_put_contents($this->csv, implode("\n", $lines) . "\n");
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:rekey-sowprog'));
        $tester->execute(['csv' => $this->csv, ...$input]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
