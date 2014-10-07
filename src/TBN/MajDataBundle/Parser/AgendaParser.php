<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\AgendaBundle\Entity\Agenda;

/*
 * Classe abstraite représentant le parse des données d'un site Internet
 * Plusieurs moyens sont disponibles: Récupérer directement les données suivant
 * une URL donnée, ou bien retourner un tableau d'URLS à partir d'un flux RSS
 *
 * @author Guillaume SAINTHILLIER
 */



abstract class AgendaParser {

    /*
     * Url du site à parser
     */
    protected $url;

    /*
     * @var $repo AgendaRepository
     */
    protected $repo;

    public function __construct(AgendaRepository $repo, $url)
    {
        $this->repo = $repo;
        $this->url = $url;

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
    protected function getAgendaFromUniqueInfo($nom, $dateDebut)
    {
        $agenda = null;
        if($dateDebut !== null and $dateDebut !== false)
        {
            $agenda = $this->repo->findOneBy([
                "nom" => $nom,
                "dateDebut" => $dateDebut
            ]); //On cherche par le nom
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

    /**
     * @return Agenda[] un tableau d'Agenda parsé
     */
    public abstract function parse();

    /*
     * @return string le nom de la classe
     */
    public abstract function getNomData();
}
