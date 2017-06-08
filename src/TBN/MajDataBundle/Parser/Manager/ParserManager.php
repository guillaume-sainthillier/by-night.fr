<?php

/*
 * Effectue la gestion des différents parser
 */

namespace TBN\MajDataBundle\Parser\Manager;

use TBN\MajDataBundle\Parser\ParserInterface;
use TBN\MajDataBundle\Utils\Monitor;

/**
 * @author Guillaume SAINTHILLIER
 */
class ParserManager
{
    protected $parsers;

    public function __construct()
    {
        $this->parsers = [];
    }

    public function add(ParserInterface $parser)
    {
        $this->parsers[] = $parser;

        return $this;
    }

    public function getAgendas()
    {
        $full_agendas = [];

        foreach ($this->parsers as $parser) {
            Monitor::writeln(sprintf(
                'Lancement de <info>%s</info>',
                $parser->getNomData()
            ));
            $agendas = $parser->parse();

            if (count($this->parsers) > 1) {
                Monitor::writeln(sprintf(
                    '<info>%d</info> événements à traiter pour <info>%s</info>',
                    count($agendas),
                    $parser->getNomData()
                ));
            }

            foreach ($agendas as $agenda) {
                $agenda->setFromData($parser->getNomData());
            }

            $full_agendas = array_merge($full_agendas, $agendas);
        }

        Monitor::writeln(sprintf(
            '<info>%d</info> événements à traiter au total',
            count($full_agendas)
        ));

        return $full_agendas;
    }
}
