<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\MajDataBundle\Utils\Monitor;


/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class FBCommand extends EventCommand
{

    protected $container;

    protected function configure()
    {
        $this
            ->setName('events:fb')
            ->setDescription('Mettre à jour les événements facebook')
            ->addOption('full', InputOption::VALUE_OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isFull = $input->getOption('full');
        $env = $input->getOption('env');
        Monitor::$output = $output;
        $doctrineHandler = $this->getContainer()->get('tbn.doctrine_event_handler');

        $nbUpdates = $doctrineHandler->updateFBEventOfWeek($isFull, $env === 'prod');

        Monitor::displayStats();

        $output->writeln(sprintf('<info>%d</info> événement(s) mis à jour', $nbUpdates));
    }
}
