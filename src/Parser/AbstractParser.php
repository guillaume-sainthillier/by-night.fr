<?php

namespace App\Parser;

/*
 * Classe abstraite représentant le parse des données d'un site Internet
 * Plusieurs moyens sont disponibles: Récupérer directement les données suivant
 * une URL donnée, ou bien retourner un tableau d'URLS à partir d'un flux RSS
 *
 * @author Guillaume SAINTHILLIER
 */

use App\Producer\EventProducer;

abstract class AbstractParser implements ParserInterface
{
    /** @var EventProducer */
    private $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function publish(array $item): void
    {
        $item['from_data'] = $this->getNomData();
        $this->eventProducer->scheduleEvent($item);
    }
}
