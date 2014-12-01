<?php

namespace TBN\MajDataBundle\Command;

use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\PlaceRepository;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Description of UpdateCommand
 *
 * @author guillaume
 */
class MigrationCommand extends EventCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('events:migrate')
                ->setDescription('Migration des lieux des événements');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        //Récupérations des dépendances
        $this->container = $this->getContainer();
        $em = $this->container->get("doctrine")->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $repoPlaces = $em->getRepository("TBNAgendaBundle:Place");

        $this->writeln($output, "Parcours des événements...");
        $agendas = $repo->findBy(["place" => null]);
        $nbMaj = count($agendas);
        foreach ($agendas as $agenda) {
            $place = $this->findPlaceBy($repoPlaces, $agenda->getSite(), $agenda->getRue(), $agenda->getLieuNom());
            
            /**
             * @var Agenda $agenda
             */
            $agenda->setPlace($place
                    ->setCodePostal($place->getCodePostal() ? $place->getCodePostal()   : $agenda->getCodePostal())
                    ->setLatitude($place->getLatitude()     ? $place->getLatitude()     : $agenda->getLatitude())
                    ->setLongitude($place->getLongitude()   ? $place->getLongitude()    : $agenda->getLongitude())
                    ->setNom($place->getNom()               ? $place->getNom()          : $agenda->getLieuNom())
                    ->setRue($place->getRue()               ? $place->getRue()          : $agenda->getRue())
                    ->setVille($place->getVille()           ? $place->getVille()        : $agenda->getVille())
                    ->setSite($place->getSite()             ? $place->getSite()         : $agenda->getSite())
            );
            
            $em->persist($agenda);
            $em->flush();
        }

        $em->flush();
        $this->writeln($output, "<info>" . $nbMaj . "</info> mises à jours");
    }
    
    protected function findPlaceBy(PlaceRepository $repo, Site $site, $rue, $lieuNom)
    {
        $place = null;
        
        if($rue !== null)
        {
            $place = $repo->findOneBy([
                "rue" => $rue,
                "site" => $site
            ]);
        }
        
        if($place === null and $lieuNom !== null)
        {
            $place = $repo->findOneBy([
                "nom" => $lieuNom,
                "site" => $site
            ]);
        }       
        
        if($place === null)
        {
            $place = new Place;
        }
        
        return $place;
    }
}
