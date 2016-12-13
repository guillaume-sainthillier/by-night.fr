<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\MajDataBundle\Utils\Monitor;


/**
 * UpdateCommand gère la commande liée à l'aggregation d'événements
 *
 * @author guillaume
 */
class ArchiveCommand extends AppCommand
{

    protected function configure()
    {
        $this
            ->setName('tbn:events:archive')
            ->setDescription('Archive les vieux événements sur By Night')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::$output = $output;
        $eventArchivator = $this->getContainer()->get('tbn.event_archivator');
        $eventArchivator->archive();
    }
}
