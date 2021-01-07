<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Event;

use App\Entity\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

class EventCheckUrlEvent extends ContractEvent
{
    private ?int $eventId = null;
    private string $eventSlug;
    private string $locationSlug;
    private string $routeName;
    private array $routeParams;
    private ?Response $response = null;
    private ?Event $event = null;

    public function __construct(?int $eventId, string $eventSlug, string $locationSlug, string $routeName, array $routeParams = [])
    {
        $this->eventId = $eventId;
        $this->eventSlug = $eventSlug;
        $this->locationSlug = $locationSlug;
        $this->routeName = $routeName;
        $this->routeParams = $routeParams;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function getEventSlug(): string
    {
        return $this->eventSlug;
    }

    public function getLocationSlug(): string
    {
        return $this->locationSlug;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }
}
