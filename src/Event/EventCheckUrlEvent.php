<?php

namespace App\Event;

use App\Entity\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

class EventCheckUrlEvent extends ContractEvent
{
    private ?int $eventId;
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

    /**
     * @param Response|null $response
     */
    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    /**
     * @param Event|null $event
     */
    public function setEvent(?Event $event): void
    {
        $this->event = $event;
    }

    /**
     * @return int|null
     */
    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    /**
     * @return string
     */
    public function getEventSlug(): string
    {
        return $this->eventSlug;
    }

    /**
     * @return string
     */
    public function getLocationSlug(): string
    {
        return $this->locationSlug;
    }

    /**
     * @return string
     */
    public function getRouteName(): string
    {
        return $this->routeName;
    }

    /**
     * @return array
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @return Response|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * @return Event|null
     */
    public function getEvent(): ?Event
    {
        return $this->event;
    }
}
