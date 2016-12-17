<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\MajDataBundle\Parser\ParserInterface;
use TBN\MajDataBundle\Utils\Monitor;


/**
 * UpdateCommand gère la commande liée à la mise à jour des événements
 *
 * @author guillaume
 */
class UpdateCommand  extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:update')
            ->setDescription('Mettee à jour les événements sur By Night')
            ->addOption('monitor', 'm', InputOption::VALUE_NONE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::enableMonitoring($input->getOption('monitor'));
        Monitor::$output = $output;

        $updater = $this->getContainer()->get('tbn.event_updater');
        $updater->update();
    }
}
