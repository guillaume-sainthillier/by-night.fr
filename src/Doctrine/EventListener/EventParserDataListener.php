<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\Event;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use App\Utils\Firewall;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EventParserDataListener
{
    public function __construct(private ParserDataRepository $parserDataRepository)
    {
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        if (!$entity instanceof Event || null === $entity->getExternalId() || null === $entity->getExternalOrigin()) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $parserData = $this->parserDataRepository->findOneBy([
            'externalOrigin' => $entity->getExternalOrigin(),
            'externalId' => $entity->getExternalId(),
        ]);

        if (null === $parserData) {
            $parserData = (new ParserData())
                ->setExternalId($entity->getExternalId())
                ->setExternalOrigin($entity->getExternalOrigin())
            ;
        }

        // Don't panic doctrine EM
        if ($parserData->getLastUpdated()?->format('Y-m-d H:i:s') !== $entity->getExternalUpdatedAt()->format('Y-m-d H:i:s')) {
            $parserData->setLastUpdated($entity->getExternalUpdatedAt());
        }

        $parserData
            ->setFirewallVersion(Firewall::VERSION)
            ->setParserVersion($entity->getParserVersion())
            ->setReason(Reject::EVENT_DELETED);

        $entityManager->persist($parserData);
    }
}
