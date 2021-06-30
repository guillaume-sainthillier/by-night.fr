<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\ExternalIdentifiableRepositoryInterface;
use App\Dto\EventDto;
use App\Repository\EventRepository;

class EventEntityProvider extends AbstractEntityProvider
{
    private EventRepository $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function supports(string $dtoClassName): bool
    {
        return EventDto::class === $dtoClassName;
    }

    protected function getRepository(): ExternalIdentifiableRepositoryInterface
    {
        return $this->eventRepository;
    }
}
