<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Archive\EventArchivator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:events:archive', 'Archive les vieux événements dans ElasticSearch')]
class EventsArchiveArchive extends Command
{
    /**
     * {@inheritdoc}
     */
    public function __construct(private EventArchivator $eventArchivator)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function configure()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventArchivator->archive();

        return Command::SUCCESS;
    }
}
