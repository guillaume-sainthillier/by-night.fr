<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Event;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

final class UserCheckUrlEvent extends ContractEvent
{
    private ?Response $response = null;

    private ?User $user = null;

    public function __construct(private readonly ?int $userId, private readonly ?string $userSlug, private readonly ?string $userUsername, private readonly string $routeName, private readonly array $routeParams = [])
    {
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUserSlug(): ?string
    {
        return $this->userSlug;
    }

    public function getUserUsername(): ?string
    {
        return $this->userUsername;
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

    public function getUser(): ?User
    {
        return $this->user;
    }
}
