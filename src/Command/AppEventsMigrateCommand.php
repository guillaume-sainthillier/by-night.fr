<?php

namespace App\Command;

use App\Entity\AdminZone;
use App\Entity\Agenda;
use App\Entity\City;
use App\Entity\Place;
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

        $nbPlaces = $em->getRepository(Place::class)
            ->createQueryBuilder('p')
            ->select('count(p)')
            ->where('p.city IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $places = $em->getRepository(Place::class)
            ->createQueryBuilder('p')
            ->select('p')
            ->where('p.country IS NULL')
            ->getQuery()
            ->iterate();

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
    }
}
