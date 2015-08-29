<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Ville;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Utils\Comparator;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\MajDataBundle\Utils\Merger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class MigrationCommand extends EventCommand {

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

    protected function configure() {
        $this
                ->setName('events:migrate')
                ->setDescription('Migration des lieux des événements');
    }
    
    /**
     * 
     * @return EntityManager
     */
    private function getManager() {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        ini_set('memory_limit', '-1');

        //Récupérations des dépendances
        
        $em	    = $this->getManager();
        $fbApi	    = $this->getContainer()->get('tbn.social.facebook_admin');
        $repo	    = $em->getRepository('TBNAgendaBundle:Agenda');
        $repoPlaces = $em->getRepository('TBNAgendaBundle:Place');
        $repoVilles = $em->getRepository('TBNAgendaBundle:Ville');
       
        $this->env	= $input->getOption('env');
        $this->handler	= $this->getContainer()->get('tbn.event_handler');

        $query          = $em->createQuery('SELECT s FROM TBNMainBundle:Site s');
        $sitesIterator  = $query->iterate();
        $batchSize = 20;
        $i = 0;
        foreach($sitesIterator as $rowSite)
        {
            $site = $rowSite[0];
            //Récupération des données existantes
            $this->writeln($output, 'Parcours des événements à '.$site->getNom().'...');
            
            $agendas = \SplFixedArray::fromArray($repo->findBy([
                'site' => $site,
                'isMigrated' => null
            ])); //On récupère les événements qui n'ont pas déjà un lieux de remplis
            
            $places = $repoPlaces->findBy(['site' => $site]);
            $villes = $repoVilles->findBy(['site' => $site]);

            $nbAgendas = $agendas->count();

	    $progress = new ProgressBar($output, ceil($nbAgendas / $batchSize));
	    $progress->start(); 
            foreach ($agendas as $i => $tmpAgenda) {

                //Création d'objet soit détruit soit persisté
                $tmpVille = (new Ville)
                        ->setNom($tmpAgenda->getVille())
                        ->setCodePostal($tmpAgenda->getCodePostal())
                ;

                $tmpPlace = (new Place)
                        ->setNom($tmpAgenda->getLieuNom() ?: $tmpVille->getNom())
                        ->setRue($tmpAgenda->getRue())
                        ->setLatitude($tmpAgenda->getLatitude())
                        ->setLongitude($tmpAgenda->getLongitude())
                        ->setVille($tmpVille)
                ;
                $tmpAgenda->setPlace($tmpPlace);

                //Gestion de la place de l'événement
                $agenda = $this->handler->handle($places, $villes, $site, $tmpAgenda);
                
                //Gestion des infos FB
		if($agenda->getFacebookEventId())
		{
		    try {
			$stats = $fbApi->getEventCountStats($agenda->getFacebookEventId());
			$agenda->setFbParticipations($stats['participations'])->setFbInterets($stats['interets']);
		    } catch (\Facebook\Exceptions\FacebookSDKException $ex) {
			$output->writeln('<error>'. $ex->getMessage() .'</error>');
		    }
		}

		$agenda->setIsMigrated(true);
		$em->persist($agenda);
                if (($i % $batchSize) === 0) {
                    $em->flush(); // Executes all insertions.
                    $progress->advance();
                }
                $i++;		
            }
            $em->flush();
            $em->clear();
	    $progress->finish();
            $this->writeln($output, "\n".'Persistance de <info>'.$nbAgendas.'</info> événement(s)...');
            $this->writeln($output, '<info>' .$nbAgendas. '</info> événement(s) mis à jour');
            $this->writeln($output, '<info>' .count($places). '</info> places mise(s) à jour');
            $this->writeln($output, '<info>' .count($villes). '</info> villes mise(s) à jour');
        }        
    }
}
