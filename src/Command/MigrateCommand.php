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
        $em       = $this->getContainer()->get('doctrine.orm.entity_manager');
        $places   = $em->getRepository('AppBundle:Place')->findBy(['city' => null]);
        $france   = $em->getRepository('AppBundle:Country')->find('FR');
        Monitor::createProgressBar(\count($places));

        $migratedPlaces = [];
        foreach ($places as $i => $place) {
            /*
             * @var Place $place
             */
            $place->setReject(new Reject())->setCountry($france);
            if ($place->getZipCity() && $place->getZipCity()->getParent()) {
                $place->setCity($place->getZipCity()->getParent());
            }

            $firewall->guessEventLocation($place);
            $place = $em->merge($place);

            $migratedPlaces[] = $place;
            Monitor::advanceProgressBar();

            if (0 === $i % 500) {
                $em->flush();
                foreach ($migratedPlaces as $migratedPlace) {
                    $em->detach($migratedPlace);
                }
                $migratedPlaces = [];
            }
        }
        $em->flush();

        $mapping = [
            'basse-terre'    => 'basse-terre',
            'bordeaux'       => 'bordeaux',
            'brest'          => 'brest',
            'caen'           => 'caen',
            'cayenne'        => 'cayenne',
            'dijon'          => 'dijon',
            'fort-de-france' => 'fort-de-france',
            'grenoble'       => 'grenoble',
            'le-havre'       => 'le-havre',
            'lille'          => 'lille',
            'lyon'           => 'lyon',
            'mamoudzou'      => 'mamoudzou',
            'marseille'      => 'marseille',
            'montpellier'    => 'montpellier',
            'nantes'         => 'nantes',
            'narbonne'       => 'narbonne',
            'nice'           => 'nice',
            'paris'          => 'paris',
            'perpignan'      => 'perpignan',
            'poitiers'       => 'poitiers',
            'reims'          => 'reims',
            'rennes'         => 'rennes',
            'rouen'          => 'rouen',
            'saint-denis'    => 'saint-denis-8',
            'strasbourg'     => 'strasbourg',
            'toulouse'       => 'toulouse',
        ];

        $places = $em->getRepository('AppBundle:Place')->findBy(['city' => null]);
        foreach ($places as $place) {
            $newCity = $em->getRepository('AppBundle:City')->findBySlug($mapping[$place->getSite()->getSubdomain()]);
            $place->setCity($newCity)->setJunk(true);
            $em->persist($place);
        }
        $em->flush();

        $users = $em->getRepository('AppBundle:User')->findBy(['city' => null]);
        foreach ($users as $user) {
            $city = $em->getRepository('AppBundle:City')->findBySlug($mapping[$user->getSite()->getSubdomain()]);
            $user->setCity($city);
            $em->persist($user);
        }
        $em->flush();
    }
}
