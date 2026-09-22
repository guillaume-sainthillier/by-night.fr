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
use App\Tests\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class EventsBackfillWebsitesCommandTest extends AppKernelTestCase
{
    public function testCleansTheWebsitesLikeImportsDo(): void
    {
        $clean = EventFactory::createOne(['websiteContacts' => ['https://example.org', 'www.b.fr']]);
        $packed = EventFactory::createOne(['websiteContacts' => ['www.abc-toulouse.fr www.fifigrot.com']]);
        $mixed = EventFactory::createOne(['websiteContacts' => ['www.c.fr', 'javascript:alert(1)', 'www.c.fr']]);
        $garbage = EventFactory::createOne(['websiteContacts' => ['92.05.40.65']]);
        $none = EventFactory::createOne(['websiteContacts' => null]);

        // The fixtures above are stale in the identity map; the command must load fresh rows
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $this->assertStringContainsString('3 event(s) cleaned.', $this->doRunCommand([])->getDisplay());
        $this->assertSame(['https://example.org', 'www.b.fr'], EventFactory::find($clean->getId())->getWebsiteContacts());
        $this->assertSame(['www.abc-toulouse.fr', 'www.fifigrot.com'], EventFactory::find($packed->getId())->getWebsiteContacts());
        $this->assertSame(['www.c.fr'], EventFactory::find($mixed->getId())->getWebsiteContacts());
        $this->assertNull(EventFactory::find($garbage->getId())->getWebsiteContacts());
        $this->assertNull(EventFactory::find($none->getId())->getWebsiteContacts());

        // Idempotent: a second run finds nothing to do
        $this->assertStringContainsString('0 event(s) cleaned.', $this->doRunCommand([])->getDisplay());
    }

    public function testDryRunListsTheChangesWithoutWritingThem(): void
    {
        $packed = EventFactory::createOne(['websiteContacts' => ['www.abc-toulouse.fr www.fifigrot.com']]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $display = $this->doRunCommand(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE])->getDisplay();

        $this->assertStringContainsString(\sprintf('#%d ["www.abc-toulouse.fr www.fifigrot.com"] => ["www.abc-toulouse.fr","www.fifigrot.com"]', $packed->getId()), $display);
        $this->assertStringContainsString('Dry run: nothing was written.', $display);
        $this->assertSame(['www.abc-toulouse.fr www.fifigrot.com'], EventFactory::find($packed->getId())->getWebsiteContacts());
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     */
    private function doRunCommand(array $input, array $options = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:events:backfill-websites'));
        $tester->execute($input, $options);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }
}
