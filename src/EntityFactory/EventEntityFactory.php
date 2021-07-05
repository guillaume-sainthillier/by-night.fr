<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\EventDto;
use App\Entity\Event;
use App\Handler\EntityProviderHandler;

class EventEntityFactory implements EntityFactoryInterface
{
    /** @var EntityProviderHandler */
    private $entityProviderHandler;

    public function __construct(EntityProviderHandler $entityProviderHandler)
    {
        $this->entityProviderHandler = $entityProviderHandler;
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
    public function create(?object $entity, object $dto): object
    {
        $entity = $entity ?? new Event();
        \assert($entity instanceof Event);
        \assert($dto instanceof EventDto);

        dd($dto);
        $entity->setExternalId($dto->externalId);

        return $entity;
    }
}
