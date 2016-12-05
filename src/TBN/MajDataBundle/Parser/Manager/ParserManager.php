<?php

/*
 * Effectue la gestion des différents parser
 */

namespace TBN\MajDataBundle\Parser\Manager;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\MajDataBundle\Parser\ParserInterface;

/**
 *
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

    public function getAgendas(OutputInterface $output)
    {
        $full_agendas = [];

        foreach ($this->parsers as $parser) {
            $this->writeln($output, "Lancement de <info>" . $parser->getNomData() . "</info>...");
            $agendas = $parser->setOutput($output)->parse();

            if (count($this->parsers) > 1) {
                $this->writeln($output, "<info>" . count($agendas) . "</info> événements à traiter pour " . $parser->getNomData());
            }

            foreach ($agendas as $agenda) {
                $agenda
                    ->setFromData($parser->getNomData());
            }

            $full_agendas = array_merge($full_agendas, $agendas);
        }

        $this->writeln($output, "<info>" . count($full_agendas) . "</info> événements à traiter au total");
        return $full_agendas;
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
