<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\DeletedPage;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Entity\User;
use App\Enum\DeletedPageKind;
use App\Repository\DeletedPageRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Records the page of every event, member, publisher and venue deleted, however it is (its author, a takedown, a merge,
 * a cleanup command), in the same transaction as the deletion: their URL then answers 410 Gone. A merged venue's and a
 * taken-over publisher's former slugs still redirect, their legacy slug being looked up first. The events and members
 * deleted before were recorded by Version20261025120725, from the gaps of their ids.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class DeletedPageListener
{
    public function __construct(private DeletedPageRepository $deletedPageRepository)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        /** @var array<string, array<string, DeletedPage>> $pages by kind, then identifier: once each */
        $pages = [];
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $page = match (true) {
                $entity instanceof Event && null !== $entity->getId() => new DeletedPage(DeletedPageKind::Event, (string) $entity->getId()),
                $entity instanceof User && null !== $entity->getId() => new DeletedPage(DeletedPageKind::User, (string) $entity->getId()),
                $entity instanceof Publisher && null !== $entity->getSlug() => new DeletedPage(DeletedPageKind::Publisher, $entity->getSlug()),
                $entity instanceof Place && null !== $entity->getSlug() && null !== $entity->getLocationSlug() => new DeletedPage(DeletedPageKind::Place, DeletedPageRepository::placeIdentifier($entity->getLocationSlug(), $entity->getSlug())),
                default => null,
            };

            if (null !== $page) {
                $pages[$page->getKind()->value][$page->getIdentifier()] = $page;
            }
        }

        if ([] === $pages) {
            return;
        }

        $metadata = $entityManager->getClassMetadata(DeletedPage::class);
        foreach ($pages as $kind => $pagesOfKind) {
            // A slug taken again, then deleted again: one query a kind, a cleanup deletes thousands of events at once
            $recorded = array_flip($this->deletedPageRepository->findRecordedIdentifiers(DeletedPageKind::from($kind), array_map('strval', array_keys($pagesOfKind))));
            foreach ($pagesOfKind as $identifier => $page) {
                if (isset($recorded[$identifier])) {
                    continue;
                }

                $entityManager->persist($page);
                $unitOfWork->computeChangeSet($metadata, $page);
            }
        }
    }
}
