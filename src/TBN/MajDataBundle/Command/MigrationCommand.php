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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \SplFixedArray;

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

    protected function execute(InputInterface $input, OutputInterface $output) {
        ini_set('memory_limit', '-1');

        //Récupérations des dépendances        
        $em	    = $this->getContainer()->get('doctrine')->getManager();
        $fbApi	    = $this->getContainer()->get('tbn.social.facebook_admin');
        $repo	    = $em->getRepository('TBNAgendaBundle:Agenda');
        $repoPlaces = $em->getRepository('TBNAgendaBundle:Place');
        $repoVilles = $em->getRepository('TBNAgendaBundle:Ville');
        $repoSites  = $em->getRepository('TBNMainBundle:Site');
       
        $this->env	= $input->getOption('env');
        $this->handler	= $this->getContainer()->get('tbn.event_handler');

	$maxItems	= 1000;
        $sites = $repoSites->findAll();
        foreach($sites as $i => $site)
        {
            //Récupération des données existantes
            $this->writeln($output, 'Parcours des événements à '.$site->getNom().'...');
            $agendas = \SplFixedArray::fromArray($repo->findBy([
                'site' => $site,
                'isMigrated' => null],
	    null, $maxItems)); //On récupère les événements qui n'ont pas déjà un lieux de remplis
            
            $places = $repoPlaces->findBy(['site' => $site]);
            $villes = $repoVilles->findBy(['site' => $site]);

            $nbAgendas = $agendas->count();

	    $progress = new ProgressBar($output, $nbAgendas);
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
		    } catch (\Facebook\FacebookSDKException $ex) {
			$output->writeln('<error>'. $ex->getMessage() .'</error>');
		    }
		}

		$agenda->setIsMigrated(true);
		$em->persist($agenda);

		$progress->advance();

                if($i === $nbAgendas)
                {
                    break;
                }
            }

	    $progress->finish();
            $this->writeln($output, "\n".'Persistance de <info>'.$nbAgendas.'</info> événement(s)...');
            $em->flush();
            $this->writeln($output, '<info>' .$nbAgendas. '</info> événement(s) mis à jour');
            $this->writeln($output, '<info>' .count($places). '</info> places mise(s) à jour');
            $this->writeln($output, '<info>' .count($villes). '</info> villes mise(s) à jour');
        }        
    }
}
