<?php

/*
 * Effectue la gestion des différents parser
 */

namespace TBN\MajDataBundle\ParserManager;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\MajDataBundle\Parser\AgendaParser;

/**
 *
 * @author Guillaume SAINTHILLIER
 */
class ParserManager {

    protected $parsers;

    protected $blackLists;

    public function __construct() {
        $this->parsers      = [];
        $this->blackLists   = [];
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
                $a->setFromData($parser->getNomData());
            }

            $tab_full_agendas = array_merge($tab_full_agendas, $tab_agendas);
        }
        return $tab_full_agendas;
    }

    public function parse(OutputInterface $output) {
        $tab_full_agendas       = [];

        foreach ($this->parsers as $parser) {
            $this->writeln($output, "Lancement de <info>".$parser->getNomData()."</info>...");
            $tab_agendas        = $parser->parse($output);
            $this->writeln($output, "<info>".count($tab_agendas)."</info> événements à traiter pour ".$parser->getNomData());
            
            foreach ($tab_agendas as $a) {
                $a->setFromData($parser->getNomData());
            }
            
            $tab_full_agendas   = array_merge($tab_full_agendas, $tab_agendas);
            $this->blackLists   = array_merge($this->blackLists, $parser->getBlackLists());
        }

        $this->writeln($output, "<info>".count($tab_full_agendas)."</info> événements à traiter au total");
        return $tab_full_agendas;
    }

    public function getBlackLists() {
        return $this->blackLists;
    }

    protected function writeln(OutputInterface $output, $text)
    {
        $output->writeln(utf8_decode($text));
    }
    protected function write(OutputInterface $output, $text)
    {
        $output->write(utf8_decode($text));
    }
}
