<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Entity\Event;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use App\Utils\Firewall;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EventParserDataListener
{
    private ParserDataRepository $parserDataRepository;

    public function __construct(ParserDataRepository $parserDataRepository)
    {
        $this->parserDataRepository = $parserDataRepository;
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof Event || null === $entity->getExternalId()) {
            return;
        }
        $entityManager = $args->getEntityManager();
        $parserData = $this->parserDataRepository->findOneBy([
            'externalId' => $entity->getExternalId(),
        ]);

        if ($parserData === null) {
            $parserData = (new ParserData())->setExternalId($entity->getExternalId());
        }

        $parserData
            ->setFirewallVersion(Firewall::VERSION)
            ->setParserVersion($entity->getParserVersion())
            ->setLastUpdated($entity->getExternalUpdatedAt())
            ->setReason(Reject::EVENT_DELETED);

        $entityManager->persist($parserData);
    }
}
