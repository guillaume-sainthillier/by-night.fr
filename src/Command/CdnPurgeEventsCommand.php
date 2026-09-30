<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Cdn\CloudflareCdnPurger;
use App\Cdn\EventPageCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drops every event page from Cloudflare in one purge call (their "event" Cache-Tag), e.g. after a release that
 * changes what an event page shows: Cloudflare keeps them up to a week otherwise (EventPageCache).
 */
#[AsCommand('app:cdn:purge-events', 'Purge every event page from the Cloudflare cache (one API call)')]
final class CdnPurgeEventsCommand extends Command
{
    public function __construct(
        private readonly CloudflareCdnPurger $cdnPurger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cdnPurger->purgeTags([EventPageCache::TAG]);

        new SymfonyStyle($input, $output)->success('Every event page is purged from Cloudflare.');

        return Command::SUCCESS;
    }
}
