<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Utils\Monitor;

class UpdateCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:update')
            ->setDescription('Mettre à jour les événements sur By Night')
            ->addOption('monitor', 'm', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::enableMonitoring($input->getOption('monitor'));
        Monitor::$output = $output;

        $updater = $this->getContainer()->get('tbn.user_updater');
        $updater->update();

        $updater = $this->getContainer()->get('tbn.event_updater');
        $updater->update();
    }
}
