<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\EventDto;
use App\Repository\EventRepository;

class EventEntityProvider extends AbstractEntityProvider
{
    public function __construct(private EventRepository $eventRepository)
    {
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
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->eventRepository;
    }
}