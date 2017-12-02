<?php

namespace AppBundle\Command;

use AppBundle\Utils\Monitor;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppCommand extends ContainerAwareCommand
{
    /**
     * @inheritdoc
     */
    protected function configure() {
        $this->addOption('monitor', 'm', InputOption::VALUE_NONE);
    }

    /**
     * @inheritdoc
     */
    public function run(InputInterface $input, OutputInterface $output) {
        Monitor::$output = $output;
        Monitor::enableMonitoring($input->hasOption('monitor') && $input->getOption('monitor'));
        Monitor::bench($this->getName(), function() use($input, $output) {
            parent::run($input, $output);
        });
        Monitor::displayStats();
    }
}
