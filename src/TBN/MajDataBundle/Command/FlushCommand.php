<?php

namespace TBN\MajDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class FlushCommand extends EventCommand
{

    protected $container;
    
    protected function configure()
    {
        $this
            ->setName('events:flush')
            ->setDescription(\utf8_decode('Supprimer les événements indésirables'))
            ->addArgument('site',       InputArgument::REQUIRED,    \utf8_decode('Quel site voulez-vous mettre à jour ?'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        //Récupération des arguments / options
        $subdomainSite      = $input->getArgument('site');

        //Récupérations des dépendances
        $this->container    = $this->getContainer();
        $em                 = $this->container->get("doctrine")->getManager();
        $repo               = $em->getRepository("TBNAgendaBundle:Agenda");
        $repoSite           = $em->getRepository("TBNMainBundle:Site");
        
	
        //Récupération du site demandé
        $site               = $repoSite->findOneBy(["subdomain" => $subdomainSite]);

        if(! $site)
        {
            $this->writeln($output, "Le site <error>".$subdomainSite."</error> est introuvable");
        }else
        {
            $this->write($output, "Recherche d'événements indésirables...");
            $agendas	= $repo->findBy(["site" => $site]);
            $nbSpam = 0;
            foreach($agendas as $agenda)
            {
                if($this->isSpam($agenda))
                {
                    $em->remove($agenda);
                    $nbSpam++;
                }
            }

            $em->flush();
            $this->writeln($output, "<info>".$nbSpam."</info> événéments supprimés");
        }
    }
}
