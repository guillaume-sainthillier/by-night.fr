<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use App\Contracts\DependencyObjectInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Entity\User;

/**
 * @implements DtoEntityIdentifierResolvableInterface<User>
 */
final class UserDto implements DependencyObjectInterface, DtoEntityIdentifierResolvableInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    public ?int $entityId = null;

    public function getKeyPrefix(): string
    {
        return 'user';
    }

    public function getUniqueKey(): string
    {
        return
            $this->getInternalId()
            ?? \sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_id($this)
            );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        $this->entityId = $entity->getId();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return \sprintf(
            '%s-id-%s',
            $this->getKeyPrefix(),
            $this->entityId
        );
    }
}
