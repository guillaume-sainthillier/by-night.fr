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
use App\Dto\UserDto;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserEntityProvider extends AbstractEntityProvider
{
    public function __construct(EntityManagerInterface $entityManager, private UserRepository $userRepository)
    {
        parent::__construct($entityManager);
    }

    public function supports(string $dtoClassName): bool
    {
        return UserDto::class === $dtoClassName;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->userRepository;
    }
}
