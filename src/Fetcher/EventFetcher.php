<?php

namespace App\Fetcher;

use App\Parser\Manager\ParserManager;
use App\Parser\ParserInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/11/2016
 * Time: 13:17.
 */
class EventFetcher
{
    /**
     * @var ParserManager
     */
    protected $parserManager;

    public function __construct(ParserManager $parserManager)
    {
        $this->parserManager = $parserManager;
    }

    /**
     * @return array
     */
    public function fetchEvents(ParserInterface $parser)
    {
        $this->parserManager->add($parser);

        return $this->parserManager->getEvents();
    }
}
