<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use TBN\AgendaBundle\Entity\Place;
use TBN\MajDataBundle\Utils\Comparator;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\MajDataBundle\Utils\Merger;
use Doctrine\ORM\EntityManager;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\MajDataBundle\Utils\Monitor;

/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class MigrationCommand extends EventCommand
{

    /**
     *
     * @var Comparator $comparator
     */
    protected $comparator;

    /**
     *
     * @var Firewall $firewall
     */
    protected $firewall;

    /**
     *
     * @var Merger $merger
     */
    protected $merger;


    protected $env;

    protected function configure()
    {
        $this
            ->setName('events:migrate')
            ->setDescription('Migration des lieux des événements');
    }

    /**
     *
     * @return EntityManager
     */
    private function getManager()
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    private function test($a) {
        return $a;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Monitor::$output = $output;
        Monitor::$log = false;

        //Récupérations des dépendances
        $em = $this->getManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        $this->env = $input->getOption('env');
        $doctrineHandler = $this->getContainer()->get('tbn.doctrine_event_handler');

        $query = $em->createQuery('SELECT s FROM TBNMainBundle:Site s');
        $sitesIterator = $query->iterate();
        $batchSize = 100;

        foreach ($sitesIterator as $rowSite) {
            $site = $rowSite[0];
            //Récupération des données existantes
            $this->writeln($output, 'Parcours des événements à ' . $site->getNom() . '...');

            $doctrineHandler->init($site);
            $agendas = $repo->findBy([
                'site' => $site->getId(),
                'isMigrated' => null
            ]); //On récupère les événements qui n'ont pas déjà un lieux de remplis
            $em->clear();
            $nbAgendas = count($agendas);

            $progress = new ProgressBar($output, ceil($nbAgendas / $batchSize));
            $progress->start();
            foreach ($agendas as $i => $tmpAgenda) {

                $tmpPlace = (new Place)
                    ->setNom($tmpAgenda->getLieuNom() ?: $tmpAgenda->getVille())
                    ->setRue($tmpAgenda->getRue())
                    ->setLatitude($tmpAgenda->getLatitude())
                    ->setLongitude($tmpAgenda->getLongitude())
                    ->setVille($tmpAgenda->getVille())
                    ->setCodePostal($tmpAgenda->getCodePostal())
                    ->setSite($tmpAgenda->getSite());
                $tmpAgenda->setPlace($tmpPlace);
                $tmpAgenda->setMigrated(true);

                $doctrineHandler->handle($tmpAgenda);
                if (($i % $batchSize) === ($batchSize - 1)) {
                    $doctrineHandler->flush();
                    $progress->advance();
                }
            }
            $doctrineHandler->flush();
            $progress->finish();
            $this->writeln($output, '');

            $this->displayStats($output, $doctrineHandler);
            $this->writeln($output, '<info>' . $nbAgendas . '</info> événement(s) mis à jour');
        }
    }
}
