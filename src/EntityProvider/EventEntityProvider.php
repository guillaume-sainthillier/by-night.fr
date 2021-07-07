<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;
use App\Dto\EventDto;
use App\Repository\EventRepository;

class EventEntityProvider extends AbstractEntityProvider
{
    private EventRepository $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass): bool
    {
        return EventDto::class === $resourceClass;
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity): void
    {
        if ($entity instanceof ExternalIdentifiablesInterface) {
            $externals = $entity->getExternalIdentifiables();
        } elseif ($entity instanceof ExternalIdentifiableInterface) {
            $externals = [$entity];
        } else {
            throw new \LogicException('Unable to fetch external ids from "%s" class', \get_class($entity));
        }

        foreach ($externals as $external) {
            \assert($external instanceof ExternalIdentifiableInterface);
            $key = $this->getEntityKey($external);

            $this->entities[$key] = $entity;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        \assert($dto instanceof ExternalIdentifiableInterface);

        $key = $this->getEntityKey($dto);
        if (!isset($this->entities[$key])) {
            return null;
        }

        return $this->entities[$key];
    }

    private function getEntityKey(ExternalIdentifiableInterface $object): string
    {
        if (null === $object->getExternalId() || null === $object->getExternalOrigin()) {
            return sprintf('hash-%s', spl_object_hash($object));
        }

        return sprintf('%s-%s', $object->getExternalId(), $object->getExternalOrigin());
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(): DtoFindableRepositoryInterface
    {
        return $this->eventRepository;
    }
}
