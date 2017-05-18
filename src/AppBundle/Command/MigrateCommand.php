<?php

namespace AppBundle\Command;


use AppBundle\Entity\Place;
use AppBundle\Reject\Reject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\Utils\Monitor;


class MigrateCommand extends AppCommand
{
    protected function configure()
    {
        $this
            ->setName('tbn:events:migrate')
            ->setDescription('Migrer les événements sur By Night')
            ->addOption('monitor', 'm', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::enableMonitoring($input->getOption('monitor'));
        Monitor::$output = $output;

        $firewall = $this->getContainer()->get('tbn.doctrine_event_handler');

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $places = $em->getRepository("AppBundle:Place")->findAll();
        $france = $em->getRepository("AppBundle:Country")->find("FR");
        Monitor::createProgressBar(count($places));

        $migratedPlaces = [];
        foreach($places as $i => $place) {
            /**
             * @var Place $place
             */
            $place->setReject(new Reject())->setCountry($france);
            $firewall->guessEventLocation($place);
            $em->persist($place);


            $migratedPlaces[] = $place;
            Monitor::advanceProgressBar();

            if($i % 500 === 0) {
                $em->flush();
                foreach($migratedPlaces as $migratedPlace) {
                    $em->detach($migratedPlace);
                }
                $migratedPlaces = [];
            }
        }
        $em->flush();
    }
}
