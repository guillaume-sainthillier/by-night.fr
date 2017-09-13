<?php

namespace AppBundle\Command;

use AppBundle\Parser\Common\FaceBookParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Parser\ParserInterface;

class FetchCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:fetch')
            ->setDescription('Récupérer des nouveaux événéments sur By Night')
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

        $fetcher = $this->getContainer()->get('tbn.event_fetcher');
        $events  = $fetcher->fetchEvents($service);
        foreach($events as $event) {
//            $this->getContainer()->get('old_sound_rabbit_mq.add_event_producer')->publish(serialize($event));
        }

        if($service instanceof FaceBookParser) {
            foreach($service->getIdsToMigrate() as $oldValue => $newValue) {
                dump(serialize(['old' => $oldValue, 'new' => $newValue]));
                $this->getContainer()->get('old_sound_rabbit_mq.update_fb_id_producer')->publish(serialize(['old' => $oldValue, 'new' => $newValue]));
            }
        }
    }
}
