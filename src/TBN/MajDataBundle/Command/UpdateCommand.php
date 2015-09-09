<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;

use TBN\MajDataBundle\Entity\HistoriqueMaj;


/**
 * UpdateCommand gère la commande liée à l'aggregation d'événements
 *
 * @author guillaume
 */
class UpdateCommand extends EventCommand
{

    protected $container;
    
    protected function configure()
    {
        $this
            ->setName('events:update')
            ->setDescription('Mettre à jour les événements sur By Night')
            ->addArgument('site',       InputArgument::REQUIRED,    'Quel site voulez-vous mettre à jour ?')
            ->addArgument('parser',     InputArgument::OPTIONAL,    'Si défini, le nom du parser à lancer, sinon tous les parsers disponibles')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '-1');
        
        try {

	//Début de construction de l'historique de la MAJ
	$historique         = new HistoriqueMaj();

        //Récupération des arguments / options
        $subdomainSite      = $input->getArgument('site');
        $parserName         = $input->getArgument('parser');
        $env                = $input->getOption('env');

        //Récupérations des dépendances
        $this->container    = $this->getContainer();
        $parserManager      = $this->container->get('parser_manager');
        $em                 = $this->container->get('doctrine')->getManager();
        $firewall           = $this->container->get('tbn.firewall');
        $handler            = $this->container->get('tbn.event_handler');

        //Récupération des repos
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        $repo               = $em->getRepository('TBNAgendaBundle:Agenda');
        $repoPlace          = $em->getRepository('TBNAgendaBundle:Place');
        $repoVille          = $em->getRepository('TBNAgendaBundle:Ville');
        $repoSite           = $em->getRepository('TBNMainBundle:Site');
        $repoSiteInfo       = $em->getRepository('TBNUserBundle:SiteInfo');
	
        //Récupération du site demandé par l'user
        $site               = $repoSite->findOneBy(['subdomain' => $subdomainSite]);
        $siteInfo           = $repoSiteInfo->findOneBy([]);
        
        //Chargement des explorations existantes en base
        $firewall->loadExplorations();

	if($site === null)
        {
            throw new RuntimeException(sprintf('<error>Le site %s demandé est introuvable en base</error>', $subdomainSite));
        }

        //Définition des parsers disponibles pour chaque site
        $parsers = [
            'toulouse' => [
                'toulouse',
                'tourisme',
                'bikini',
                'dynamo',
		'soonnight',
                'facebook',
            ],
            'paris' => [
                'tourisme',
                'soonnight',
                'facebook'
            ]
        ];
        
        $this->writeln($output, sprintf('<info>[%s]</info> Mise à jour de <info>%s By Night</info>...', $env, $site->getNom()));

        //Récupération des parsers
        foreach($parsers[$subdomainSite] as $serviceName)
        {
            if(!$parserName || $serviceName === $parserName)
            {
                $parser = $this->container->get('tbn.parser.'.$subdomainSite.'.'.$serviceName);
		
		//Dépendances dynamiques liées aux différents parsers
		$parser->setOutput($output)->setSite($site)->setSiteInfo($siteInfo);
    
                $parserManager->add($parser);
            }
        }
        
        //Récupération des événements
        $agendas = $parserManager->getAgendas($output);

        //Récupération des places & villes
        $places = $repoPlace->findBy(['site' => $site]);
        $villes = $repoVille->findBy(['site' => $site]);

        $start = microtime(true);
        $nbUpdate = 0;
        $nbInsert = 0;
        $nbBlackList = 0;
        $batchSize = 20;
        $i = 0;
        $size = ceil(count($agendas) / $batchSize);        
        
        // Starting progress
        $progress = new ProgressBar($output, $size);
        $progress->start();
        foreach($agendas as $j => $tmpAgenda)
        {
            $start = microtime(true);
            //Gestion de l'événement + sa place et sa ville associée
            $cleanedEvent = $handler->handle($places, $villes, $site, $tmpAgenda);
            $end = microtime(true);
            $this->writeln($output, 'FLAG0 : <info>'.(($end - $start)*1000).' ms</info>');
            $start = microtime(true);
	    if(! $cleanedEvent->getDateDebut() instanceof \DateTime || ! $cleanedEvent->getDateFin() instanceof \DateTime)
	    {
		$agenda = null;
	    }else
	    {
		//Récupération des events de la même période en base
		$existingEvents = $repo->findBy([
		    'dateDebut' => $cleanedEvent->getDateDebut(),
		    'dateFin' => $cleanedEvent->getDateFin(),
		    'site' => $site
		]);

                $end = microtime(true);
                $this->writeln($output, 'FLAG1 : <info>'.(($end - $start)*1000).' ms</info>');
                $start = microtime(true);
                
                
		//Gestion de l'événement
		$agenda = $handler->handleEvent($existingEvents, $cleanedEvent);
                $end = microtime(true);
                $this->writeln($output, 'FLAG2 : <info>'.(($end - $start)*1000).' ms</info>');
                $start = microtime(true);
	    }
            
            if($agenda !== null)
            {
                //Récupération de l'image distante si besoin
                if($env !== 'dev' && $agenda->getPath() === null && $agenda->getUrl() !== null)
                {
                    $handler->downloadImage($agenda);
                }

                //Persistence en base
                $em->persist($agenda);
                if (($i % $batchSize) === 0) {
                    $end = microtime(true);
                    $progress->clear();
                    $this->writeln($output, 'Persistance : <info>'.(($end - $start)*1000).' ms</info>');
                    $start = microtime(true);
                    $em->flush(); // Executes all deletions.
                    //$em->clear(); // Detaches all objects from Doctrine!
                }
                $i++;
                
                if(! $firewall->isPersisted($agenda))
                {
                    $nbInsert++;
                }else
                {
                    $nbUpdate++;
                }
            }else
            {
                $nbBlackList++;
            }
            
            $end = microtime(true);
            $this->writeln($output, 'FLAG3 : <info>'.(($end - $start)*1000).' ms</info>');
            if(($j % $batchSize) === 0)
            {
                $progress->advance($batchSize);
            }            
        }

        $end = microtime(true);
        $progress->clear();
        $this->writeln($output, 'Fin de persistance : <info>'.(($end - $start)*1000).' ms</info>');        
	$em->flush();
        $progress->finish();

	//Gestion des explorations + historique de la maj
	$explorations = $firewall->getExplorationsToSave();
        $historique
                ->setFromData($parserName ?: 'tous')
                ->setExplorations($nbBlackList + count($explorations))
                ->setNouvellesSoirees($nbInsert)
                ->setUpdateSoirees($nbUpdate)
                ->setSite($site)
        ;

        $progress->start(ceil(count($explorations) / $batchSize));
        $i = 0;
	foreach($explorations as $exploration)
	{
	    $em->persist($exploration);
            if (($i % $batchSize) === 0) {
                $progress->advance();
                $em->flush();
            }
            $i++;
	}
	
        $em->persist($historique);
        $em->flush();
        $progress->finish();
        $progress->clear();
        $this->writeln($output, 'NEW: <info>'.$nbInsert.'</info>');
        $this->writeln($output, 'UPDATES: <info>'.$nbUpdate.'</info>');
        $this->writeln($output, 'BLACKLIST: <info>'.$nbBlackList.' + '.count($explorations).'</info>');
        
        } catch(\Exception $e)
        {
            $this->writeln($output, $e->getTraceAsString());
            throw new \Exception('Erreur dans le traitement', 0, $e);
        }
    }
}
