<?php

namespace App\Command;

use App\Utils\Monitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('monitor', 'm', InputOption::VALUE_NONE, 'Active le monitor des fonctions');
    }
}
