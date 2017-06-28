<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\MajDataBundle\Parser\ParserInterface;
use TBN\MajDataBundle\Utils\Monitor;

class FetchCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:fetch')
            ->setDescription('Récupérer des nouveaux sur By Night')
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du service à executer')
            ->addOption('monitor', 'm', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = $input->getArgument('parser');
        if (!$this->getContainer()->has($parser)) {
            throw new \LogicException(sprintf(
                'Le service "%s" est introuvable',
                $parser
            ));
        }

        $service = $this->getContainer()->get($parser);
        if (!$service instanceof ParserInterface) {
            throw new \LogicException(sprintf(
                'Le service "%s" doit être une instance de ParserInterface',
                $service
            ));
        }

        Monitor::enableMonitoring($input->getOption('monitor'));
        Monitor::$output = $output;
        $fetcher         = $this->getContainer()->get('tbn.event_fetcher');

        $events = $fetcher->fetchEvents($service);
        $this->getContainer()->get('tbn.doctrine_event_handler')->handleManyCLI($events, $service);
    }
}
