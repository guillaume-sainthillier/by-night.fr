<?php

namespace App\Parser\Manager;

use App\Parser\ParserInterface;
use App\Utils\Monitor;

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

    public function getEvents()
    {
        $full_events = [];

        foreach ($this->parsers as $parser) {
            /** @var ParserInterface $parser */
            Monitor::writeln(\sprintf(
                'Lancement de <info>%s</info>',
                $parser->getNomData()
            ));
            $events = $parser->parse();

            if (\count($this->parsers) > 1) {
                Monitor::writeln(\sprintf(
                    '<info>%d</info> événements à traiter pour <info>%s</info>',
                    \count($events),
                    $parser->getNomData()
                ));
            }

            foreach ($events as $i => $event) {
                $events[$i]['from_data'] = $parser->getNomData();
            }

            $full_events = \array_merge($full_events, $events);
        }

        Monitor::writeln(\sprintf(
            '<info>%d</info> événements à traiter au total',
            \count($full_events)
        ));

        return $full_events;
    }
}
