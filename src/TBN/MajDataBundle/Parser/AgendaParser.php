<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Entity\BlackList;

/*
 * Classe abstraite représentant le parse des données d'un site Internet
 * Plusieurs moyens sont disponibles: Récupérer directement les données suivant
 * une URL donnée, ou bien retourner un tableau d'URLS à partir d'un flux RSS
 *
 * @author Guillaume SAINTHILLIER
 */



abstract class AgendaParser {

    /**
     * Url du site à parser
     */
    protected $url;

    /**
     * @var $repo AgendaRepository
     */
    protected $repo;

    /**
     *
     * @var BlackList[]
     */
    protected $blackLists;

    public function __construct(AgendaRepository $repo, $url)
    {
        $this->repo         = $repo;
        $this->url          = $url;
        $this->blackLists   = [];

        return $this;
    }
    

    public function setURL($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getURL()
    {
        return $this->url;
    }

    protected function parseDate($date)
    {
        $tabMois = ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"];

        return preg_replace_callback("/(.+)(\d{2}) (".implode("|", $tabMois).") (\d{4})(.*)/iu",
                function($items) use($tabMois)
        {
            return $items[4]."-".(array_search($items[3],$tabMois) +1)."-".$items[2];
        }, $date);
    }

    /**
     *
     * @param string $nom
     * @param \DateTime|null $dateDebut
     * @return Agenda
     */
    protected function getAgendaFromUniqueInfo($nom, \DateTime $dateDebut, \DateTime $dateFin = null, $nomLieu = null)
    {
        $agenda = null;        
        if($dateDebut !== null && $dateDebut !== false)
        {
            if($nomLieu !== null && $dateFin !== null && $dateFin !== false)
            {
                $agenda = $this->repo->findOneByPlace($nomLieu, $dateDebut, $dateFin);
            }
            
            if($agenda === null)
            {
                $agenda = $this->repo->findOneBy([
                    "nom" => $nom,
                    "dateDebut" => $dateDebut
                ]); //On cherche par le nom
            }            
        }

        if($agenda === null)
        {
            $agenda = new Agenda;
        }

        return $agenda;
    }

    public function postParse() {
	return [];
    }

    public function getBlackLists() {
        return $this->blackLists;
    }

    protected function write(OutputInterface $output, $text)
    {
        $output->write(\utf8_decode($text));
    }

    protected function writeln(OutputInterface $output, $text)
    {
        $output->writeln(\utf8_decode($text));
    }


    /**
     * @return Agenda[] un tableau d'Agenda parsé
     */
    public abstract function parse(OutputInterface $output);

    /*
     * @return string le nom de la classe
     */
    public abstract function getNomData();
}
