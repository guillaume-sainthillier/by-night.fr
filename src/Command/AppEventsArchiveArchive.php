<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Archive\EventArchivator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsArchiveArchive extends Command
{
    private EventArchivator $eventArchivator;

    /**
     * {@inheritdoc}
     */
    public function __construct(EventArchivator $eventArchivator)
    {
        parent::__construct();

        $this->eventArchivator = $eventArchivator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:events:archive')
            ->setDescription('Archive les vieux événements dans ElasticSearch');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventArchivator->archive();

        return 0;
    }
}
