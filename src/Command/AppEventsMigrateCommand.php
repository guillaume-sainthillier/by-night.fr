<?php

namespace App\Command;

use App\Entity\AdminZone;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\ZipCity;
use App\Handler\DoctrineEventHandler;
use App\Reject\Reject;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsMigrateCommand extends AppCommand
{
    private const PLACES_PER_TRANSACTION = 500;

    /** @var DoctrineEventHandler */
    private $doctrineEventHandler;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(DoctrineEventHandler $doctrineEventHandler, EntityManagerInterface $entityManager, ?string $name = null)
    {
        parent::__construct($name);

        $this->doctrineEventHandler = $doctrineEventHandler;
        $this->entityManager        = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:events:migrate')
            ->setDescription('Migrer les événements sur By Night');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->entityManager;
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        $nbPlaces = $em->getRepository(Place::class)
            ->createQueryBuilder('p')
            ->select('count(p)')
            ->where('p.city IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $places = $em->getRepository(Place::class)
            ->createQueryBuilder('p')
            ->select('p')
            ->where('p.city IS NULL')
            ->getQuery()
            ->iterate();

        $france = $em->getRepository(Country::class)->find('FR');
        Monitor::createProgressBar($nbPlaces);
        foreach ($places as $i => $row) {
            /** @var Place $place */
            $place = $row[0];
            $place->setReject(new Reject());

            $this->doctrineEventHandler->upgrade($place);
            $em->merge($place);

            Monitor::advanceProgressBar();
            if (self::PLACES_PER_TRANSACTION - 1 === $i % self::PLACES_PER_TRANSACTION) {
                $em->flush();
                $em->clear(Place::class);
                $em->clear(ZipCity::class);
                $em->clear(AdminZone::class);
                $em->clear(City::class);
            }
        }
        $em->flush();
        $em->clear(Place::class);
        $em->clear(ZipCity::class);
        $em->clear(AdminZone::class);
        $em->clear(City::class);
        Monitor::finishProgressBar();

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
            'saint-denis'    => 'saint-denis-9',
            'strasbourg'     => 'strasbourg',
            'toulouse'       => 'toulouse',
        ];

        $places = $em->getRepository(Place::class)->findBy(['city' => null]);
        foreach ($places as $place) {
            $newCity = $em->getRepository(City::class)->findBySlug($mapping[$place->getSite()->getSubdomain()]);
            $place->setCity($newCity)->setJunk(true);
            $em->merge($place);
        }
        $em->flush();
        $em->clear();

        $users = $em->getRepository(User::class)->findBy(['city' => null]);
        foreach ($users as $user) {
            /** @var User $user */
            if($user->getSite() === null) {
                continue;
            }
            $city = $em->getRepository(City::class)->findBySlug($mapping[$user->getSite()->getSubdomain()]);
            $user->setCity($city);
            $em->merge($user);
        }
        $em->flush();
        $em->clear();
    }
}
