<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * && open the template in the editor.
 */

namespace TBN\MajDataBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\DoctrineEventHandler;
use TBN\MajDataBundle\Utils\Monitor;

/**
 * Description of EventCommand
 *
 * @author guillaume
 */
abstract class EventCommand extends ContainerAwareCommand
{

    protected function displayStats(OutputInterface $output, DoctrineEventHandler $doctrineHandler) {
        $stats = $doctrineHandler->getStats();
        $nbExplorations = $stats['nbExplorations'];
        $nbUpdate = $stats['nbUpdates'];
        $nbInsert = $stats['nbInserts'];
        $nbBlackList = $stats['nbBlacklists'];

        Monitor::displayStats();

        $this->writeln($output, sprintf('NEW: <info>%d</info>', $nbInsert));
        $this->writeln($output, sprintf('UPDATES: <info>%d</info>', $nbUpdate));
        $this->writeln($output, sprintf('BLACKLIST: <info>%d / %d</info>', $nbBlackList, $nbExplorations));

        return $stats;
    }

    /**
     * Retourne la recherche d'un doublon en fonction de la pertinance des informations
     * @param Agenda $event l'événement à rechercher
     * @param Agenda[] $agendas l'événement à rechercher
     * @return boolean vrai si un événement similaire est déjà présent, faux sinon
    */
    protected function hasSimilarEvent(Agenda $event, $agendas)
    {
	$clean_descriptif_event     = strtolower(preg_replace("/[^a-zA-Z0-9]+/u", " ", html_entity_decode($event->getDescriptif())));
	$nom_event                  = $event->getNom();
	$date_debut_event	    = $event->getDateDebut();

	if(strlen($clean_descriptif_event) <= 50) //Moins de 70 caractères, on l'ejecte
	{
	    return true;
	}

        foreach($agendas as $agenda)
        {
            $date_debut_needle  = $agenda->getDateDebut();
            $nom_needle         = trim($agenda->getNom());
            
            if($nom_needle != "" && $nom_event != "" && $date_debut_event->format("Y-m-d") === $date_debut_needle->format("Y-m-d"))
            {
                if(similar_text($nom_event, $nom_needle) > 70) // Plus de 70% de ressemblance, on l'ejecte
                {
                    return true;
                }

                if(stristr($nom_event, $nom_needle) !== false || stristr($nom_needle, $nom_event) !== false)
                {
                    return true;
                }
            }
        }

        return $this->isSpam($event);
    }

    protected function isSpam(Agenda $agenda)
    {
	//Vérification des events spams
        $black_list = [
	    "Buy && sell tickets at","Please join","Invite Friends","Buy Tickets",
	    "Find Local Concerts", "reverbnation.com", "pastaparty.com", "evrd.us",
	    "farishams.com", "tinyurl.com", "bandcamp.com", "ty-segall.com",
	    "fritzkalkbrenner.com", "campusfm.fr", "polyamour.info", "parislanuit.fr",
	    "Please find the agenda", "Fore More Details like our Page & Massage us"
	];

	$terms = array_map('preg_quote', $black_list);

        return preg_match("/".implode("|", $terms)."/iu", $agenda->getDescriptif());
    }

    protected function cleanEvents($agendas)
    {
	$clean_agendas = [];
	foreach($agendas as $agenda)
	{
	    if(! $this->hasSimilarEvent($agenda, $clean_agendas))
	    {
		$clean_agendas[] = $this->cleanEvent($agenda);
	    }
	}

	return $clean_agendas;
    }

    protected function cleanEvent(Agenda $agenda)
    {
	if(in_array(strtolower($agenda->getTarif()), ['gratuit']))
	{
	    $agenda->setTarif(null);
	}
	$descriptif = $this->stripTags($this->stipStyles($agenda->getDescriptif()));
	return $agenda->setDescriptif($descriptif);
    }

    protected function stripTags($text)
    {
        return trim(htmlspecialchars_decode($text));
    }

    protected function stipStyles($tag)
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i",'<$1$2>', $tag);
    }

    /*
     * Retourne les données d'une URL
    */
    protected function getData($url)
    {
        return file_get_contents($url);
    }

    protected function writeln(OutputInterface $output, $text)
    {
        $output->writeln($text);
    }

    protected function write(OutputInterface $output, $text)
    {
        $output->write($text);
    }
}
