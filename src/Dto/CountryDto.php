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
use App\Entity\Country;

/**
 * @implements DtoEntityIdentifierResolvableInterface<Country>
 */
final class CountryDto implements DependencyObjectInterface, DtoEntityIdentifierResolvableInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    public ?string $entityId = null;

    public ?string $code = null;

    public ?string $name = null;

    public function getKeyPrefix(): string
    {
        return 'country';
    }

    public function getUniqueKey(): string
    {
        if (null === $this->code && null === $this->name) {
            return \sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_id($this)
            );
        }

        return \sprintf(
            '%s-data-%s',
            $this->getKeyPrefix(),
            mb_strtolower((string) ($this->code ?? $this->name))
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
