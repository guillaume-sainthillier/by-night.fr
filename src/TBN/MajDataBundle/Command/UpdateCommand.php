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
        set_time_limit(0);
        ini_set('memory_limit', '-1');

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
        $repo               = $em->getRepository('TBNAgendaBundle:Agenda');
        $repoPlace          = $em->getRepository('TBNAgendaBundle:Place');
        $repoVille          = $em->getRepository('TBNAgendaBundle:Ville');
        $repoSite           = $em->getRepository('TBNMainBundle:Site');
        $repoSiteInfo       = $em->getRepository('TBNUserBundle:SiteInfo');
	
        //Récupération du site demandé par l'user
        $site               = $repoSite->findOneBy(['subdomain' => $subdomainSite]);
        $siteInfo           = $repoSiteInfo->findOneBy([]);

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
        foreach($agendas as $tmpAgenda)
        {
            //Gestion de l'événement + sa place et sa ville associée
            $cleanedEvent = $handler->handle($places, $villes, $site, $tmpAgenda);

            //Récupération des events de la même période en base
            $existingEvents = $repo->findBy([
                'dateDebut' => $cleanedEvent->getDateDebut(),
                'dateFin' => $cleanedEvent->getDateFin(),
                'site' => $site
            ]);

            //Gestion de l'événement & persistance si besoin 
            $agenda = $handler->handleEvent($existingEvents, $cleanedEvent);
            if($agenda !== null)
            {
                //Récupération de l'image distante si besoin
                if($env !== 'dev2' && $agenda->getPath() === null && $agenda->getUrl() !== null)
                {
                    $handler->downloadImage($agenda);
                }

                //Persistence en base
                $em->persist($agenda);
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
        }

        $end = microtime(true);
        $this->writeln($output, 'GESTION : <info>'.($end - $start).' ms</info>');
	$em->flush();

	//Gestion des blackLists + historique de la maj
	$blackLists = $firewall->getBlackList();
        $historique
                ->setFromData($parserName ?: 'tous')
                ->setBlackLists($nbBlackList + count($blackLists))
                ->setNouvellesSoirees($nbInsert)
                ->setUpdateSoirees($nbUpdate)
                ->setSite($site)
        ;

	foreach($blackLists as $blackList)
	{
	    $em->persist($blackList);
	}
	
        $em->persist($historique);
        $em->flush();
        $this->writeln($output, 'NEW: <info>'.$nbInsert.'</info>');
        $this->writeln($output, 'UPDATES: <info>'.$nbUpdate.'</info>');
        $this->writeln($output, 'BLACKLIST: <info>'.$nbBlackList.' + '.count($blackLists).'</info>');         
    }
}
