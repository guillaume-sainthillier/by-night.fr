<?php

namespace AppBundle\Fetcher;

use Doctrine\Common\Persistence\ObjectManager;
use AppBundle\Parser\Manager\ParserManager;
use AppBundle\Parser\ParserInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/11/2016
 * Time: 13:17.
 */
class EventFetcher
{
    /**
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @var ParserManager
     */
    protected $parserManager;

    public function __construct(ParserManager $parserManager, ObjectManager $entityManager)
    {
        $this->parserManager = $parserManager;
        $this->entityManager = $entityManager;
    }

    /**
     * @param ParserInterface $parser
     *
     * @return array
     */
    public function fetchEvents(ParserInterface $parser)
    {
        $this->parserManager->add($parser);

        return $this->parserManager->getAgendas();
    }
}
