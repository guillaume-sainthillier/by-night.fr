<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use App\Contracts\DependencyObjectInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Entity\Country;

class CountryDto implements DependencyObjectInterface, DtoEntityIdentifierResolvableInterface, InternalIdentifiableInterface
{
    /** @var string|null */
    public $entityId;

    /** @var string|null */
    public $code;

    /** @var string|null */
    public $name;

    public function getUniqueKey(): string
    {
        return sprintf(
            'country-u-%s',
            $this->code
            ?? $this->name
            ?? spl_object_id($this)
        );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        \assert($entity instanceof Country);
        $this->entityId = $entity->getId();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return sprintf('country-%s', $this->entityId);
    }
}
