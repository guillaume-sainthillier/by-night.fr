<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use TBN\AgendaBundle\Entity\Agenda;

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
        $repoSite           = $em->getRepository('TBNMainBundle:Site');
        $repoSiteInfo       = $em->getRepository('TBNUserBundle:SiteInfo');
	
        //Récupération du site demandé par l'user
        $site               = $repoSite->findOneBy(['subdomain' => $subdomainSite]);
        $siteInfo           = $repoSiteInfo->findOneBy([]);

	if($site === null)
        {
            throw new RuntimeException(sprintf('<error>Le site %s demandé est introuvable en base</error>', $subdomainSite));
        }
        
        //Chargement des explorations existantes en base
        $firewall->loadExplorations($site);
        $key = "TBN\MajDataBundle\Entity\Exploration";
        
        $em->clear($key);        
        //var_dump(array_keys($em->getUnitOfWork()->getIdentityMap()[$key])); die();

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
        $persistedPlaces = $this->loadPlaces($repoPlace, $site);

        $persistedEvents    = [];
        $unpersistedEvents  = [];
        $unpersistedPlaces  = [];
        
        $nbExplorations = 0;
        $nbUpdate = 0;
        $nbInsert = 0;
        $nbBlackList = 0;
        $batchSize = 100;
        $size = ceil(count($agendas) / $batchSize);        
        
        $progress = new ProgressBar($output, $size);
        $progress->start();
        foreach($agendas as $i => $agenda)
        {
            $fullPlaces = array_merge($persistedPlaces, $unpersistedPlaces);
            //Gestion de l'événement + sa place et sa ville associée
            $start = microtime(true);
            $tmpAgenda = $handler->handle($fullPlaces, $site, $agenda);
            $end = microtime(true);

	    if($tmpAgenda === null || ! $tmpAgenda->getDateDebut() instanceof \DateTime || ! $tmpAgenda->getDateFin() instanceof \DateTime)
	    {
		$tmpAgenda = null;
	    }else
	    {
		//Récupération des events de la même période en base	
                $currentPersistedEvents = $this->loadAgendasByDates($repo, $tmpAgenda, $persistedEvents);
                $key = $this->getAgendaCacheKey($tmpAgenda);
                if(! isset($unpersistedEvents[$key]))
                {
                    $unpersistedEvents[$key] = [];
                }
                $currentUnpersistedEvents = $unpersistedEvents[$key];
                
		//Gestion & filtrage de l'événement
                $fullEvents = array_merge($currentPersistedEvents, $currentUnpersistedEvents);
		$tmpAgenda = $handler->handleEvent($fullEvents, $tmpAgenda);
	    }
            
            if($tmpAgenda !== null)
            {
                //Récupération de l'image distante si besoin
                if($env !== 'dev' && $tmpAgenda->getPath() === null && $tmpAgenda->getUrl() !== null)
                {
                    $handler->downloadImage($tmpAgenda);
                }

                //Persistence en base
                $tmpAgenda->preDateModification();
                $managedAgenda = $em->merge($tmpAgenda);
                
                //MAJ des tableaux
                $this->insertOrUpdate('agenda', $managedAgenda, $currentPersistedEvents, $currentUnpersistedEvents);
                $this->postManage($managedAgenda, $persistedPlaces, $unpersistedPlaces);
                
                //Stats
                if(! $firewall->isPersisted($managedAgenda))
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
            
            //Commit
            if(($i % $batchSize) === 0)
            {
                $start = microtime(true);
                $progress->clear();
                
                //Gestion des explorations + historique de la maj
                $explorations = $firewall->getExplorationsToSave();
                $nbExplorations += count($explorations);
                foreach($explorations as $exploration)
                {
                    $em->merge($exploration);
                }               
                $em->flush();
                $firewall->flushNewExplorations(); 
                
                //Les événements sont maintenants persistés en base
                foreach($unpersistedEvents as $key => &$newPersistedEvents)
                {
                    $currentPersistedEvents = $persistedEvents[$key];
                    $this->mergeNewEntities('event', $currentPersistedEvents, $newPersistedEvents);
                }
                $this->postFlush($persistedPlaces, $unpersistedPlaces);
                $end = microtime(true);
                $progress->advance();
            }            
        }
        
        
        $end = microtime(true);
        $progress->clear();
        $this->writeln($output, 'Fin de persistance : <info>'.round((($end - $start)*1000.0)).' ms</info>');        
	$em->flush(); 
        $progress->finish();

        $explorations = $firewall->getExplorationsToSave();
        $nbExplorations += count($explorations);
	$historique
                ->setFromData($parserName ?: 'tous')
                ->setExplorations($nbBlackList + $nbExplorations)
                ->setNouvellesSoirees($nbInsert)
                ->setUpdateSoirees($nbUpdate)
                ->setSite($site)
        ;

        $progress->start(ceil(count($explorations) / $batchSize));
        $i = 0;        
	foreach($explorations as $exploration)
	{
	    $em->merge($exploration);
            if (($i % $batchSize) === 0) {
                $progress->advance();
                $em->flush();
            }
            $i++;
	}
	
        $em->merge($historique->setDateFin(new \DateTime));
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
    
    private function loadAgendasByDates($repo, Agenda $agenda, array &$persistedEvents)
    {
        //Récupération des events de la même période en base
        $key = $this->getAgendaCacheKey($agenda);
        if(! isset($persistedEvents[$key]))
        {
            $agendas = $repo->findBy([
                'dateDebut' => $agenda->getDateDebut(),
                'dateFin' => $agenda->getDateFin(),
                'site' => $agenda->getSite()
            ]);
            
            $persistedEvents[$key] = [];
            foreach($agendas as $persistedAgenda)
            {
                $persistedEvents[$key][$persistedAgenda->getId()] = $persistedAgenda;
            }
        }
        
        return $persistedEvents[$key];
    }
    
    private function getAgendaCacheKey(Agenda $agenda)
    {
        return $agenda->getSite()->getId().'.'.$agenda->getDateDebut()->format('Y-m-d').'.'.$agenda->getDateFin()->format('Y-m-d');
    }
}
