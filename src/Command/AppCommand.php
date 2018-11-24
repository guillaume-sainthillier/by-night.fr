<?php

namespace App\Command;

use App\Utils\Monitor;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('monitor', 'm', InputOption::VALUE_NONE, 'Active le monitor des fonctions');
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        Monitor::$output = $output;
        Monitor::enableMonitoring($input->hasOption('monitor') && $input->getOption('monitor'));
        $retour = Monitor::bench($this->getName(), function () use ($input, $output) {
            return parent::run($input, $output);
        });
        Monitor::displayStats();

        return $retour;
    }
}
