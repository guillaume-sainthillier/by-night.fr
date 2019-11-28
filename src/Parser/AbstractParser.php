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
use Psr\Log\LoggerInterface;

abstract class AbstractParser implements ParserInterface
{
    /** @var EventProducer */
    private $eventProducer;

    /** @var LoggerInterface */
    private $logger;

    /** @var int */
    private $parsedEvents;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        $this->logger = $logger;
        $this->eventProducer = $eventProducer;
        $this->parsedEvents = 0;
    }

    public function publish(array $item): void
    {
        $item['from_data'] = static::getParserName();
        try {
            $this->eventProducer->scheduleEvent($item);
            $this->parsedEvents++;
        } catch (\JsonException $e) {
            $this->logException($e, $item);
        }
    }

    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    protected function logException(\Throwable $e, array $context = [])
    {
        $this->logger->error($e, $context);
    }
}
