<?php

namespace App\Event;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

class UserCheckUrlEvent extends ContractEvent
{
    private ?int $userId;
    private ?string $userSlug;
    private ?string $userUsername;
    private string $routeName;
    private array $routeParams;
    private ?Response $response = null;
    private ?User $user = null;

    public function __construct(?int $userId, ?string $userSlug, ?string $userUsername, string $routeName, array $routeParams = [])
    {
        $this->userId = $userId;
        $this->userSlug = $userSlug;
        $this->userUsername = $userUsername;
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
     * @param User|null $user
     */
    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @return string|null
     */
    public function getUserSlug(): ?string
    {
        return $this->userSlug;
    }

    /**
     * @return string|null
     */
    public function getUserUsername(): ?string
    {
        return $this->userUsername;
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
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
}
