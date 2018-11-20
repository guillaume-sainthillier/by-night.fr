<?php

namespace App\Command;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Entity\User;
use App\Reject\Reject;
use App\Utils\Monitor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends AppCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tbn:events:migrate')
            ->setDescription('Migrer les événements sur By Night');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $firewall = $this->getContainer()->get('tbn.doctrine_event_handler');
        $em       = $this->getContainer()->get('doctrine.orm.entity_manager');
        $places   = $em->getRepository(Place::class)->findBy(['city' => null]);
        $france   = $em->getRepository(Country::class)->find('FR');
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

        $places = $em->getRepository(Place::class)->findBy(['city' => null]);
        foreach ($places as $place) {
            $newCity = $em->getRepository(City::class)->findBySlug($mapping[$place->getSite()->getSubdomain()]);
            $place->setCity($newCity)->setJunk(true);
            $em->persist($place);
        }
        $em->flush();

        $users = $em->getRepository(User::class)->findBy(['city' => null]);
        foreach ($users as $user) {
            /**
             * @var User
             */
            $city = $em->getRepository(City::class)->findBySlug($mapping[$user->getSite()->getSubdomain()]);
            $user->setCity($city);
            $em->persist($user);
        }
        $em->flush();
    }
}
