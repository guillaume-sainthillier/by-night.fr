<?php

/*
 * Effectue la gestion des différents parser
 */

namespace TBN\MajDataBundle\ParserManager;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\AgendaBundle\Repository\AgendaRepository;

/**
 *
 * @author Guillaume SAINTHILLIER
 */
class ParserManager {

    protected $parsers;

    public function __construct() {
        $this->parsers = [];
    }

    public function addAgendaParser(AgendaParser $parser) {
        $this->parsers[] = $parser;

        return $this;
    }

    public function postParse() {
        $tab_full_agendas = [];

        foreach ($this->parsers as $parser) {
            $tab_agendas = $parser->postParse();

            foreach ($tab_agendas as $a) {
                /* @var $a Agenda */
                $a->setFromData($parser->getNomData());
            }

            $tab_full_agendas = array_merge($tab_full_agendas, $tab_agendas);
        }
        return $tab_full_agendas;
    }

    public function parse() {
        $tab_full_agendas = [];

        foreach ($this->parsers as $parser) {
            $tab_agendas = $parser->parse();
            foreach ($tab_agendas as $a) {
                /* @var $a Agenda */
                $a->setFromData($parser->getNomData());
            }

            $tab_full_agendas = array_merge($tab_full_agendas, $tab_agendas);
        }
        return $tab_full_agendas;
    }

}
