<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Utils\Monitor;

class ArchiveCommand extends AppCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tbn:events:archive')
            ->setDescription('Archive les vieux événements sur By Night');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $eventArchivator = $this->getContainer()->get('tbn.event_archivator');
        $eventArchivator->archive();
    }
}
