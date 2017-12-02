<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * && open the template in the editor.
 */

namespace AppBundle\Command;

use AppBundle\Utils\Monitor;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppCommand extends ContainerAwareCommand
{
    protected function configure() {
        $this->addOption('monitor', 'm', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        Monitor::$output = $output;
        Monitor::enableMonitoring($input->getOption('monitor'));
    }
}
