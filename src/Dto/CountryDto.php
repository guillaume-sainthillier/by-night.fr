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
use App\Utils\ObjectKey;

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
        return Country::KEY_PREFIX;
    }

    /**
     * The ISO alpha-2 code as stored in Country::$id, or null when the feed value is
     * unusable. Feeds are not consistent ("fr", " FR ", ""), and every lookup below the
     * parsers (repository, comparator) compares against the canonical upper-case id.
     */
    public function getNormalizedCode(): ?string
    {
        if (null === $this->code) {
            return null;
        }

        $code = strtoupper(trim($this->code));

        return '' === $code ? null : $code;
    }

    public function getUniqueKey(): string
    {
        if (null === $this->code && null === $this->name) {
            return ObjectKey::transient($this->getKeyPrefix(), $this);
        }

        return ObjectKey::data($this->getKeyPrefix(), mb_strtolower((string) ($this->code ?? $this->name)));
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

        return ObjectKey::internal($this->getKeyPrefix(), $this->entityId);
    }
}
