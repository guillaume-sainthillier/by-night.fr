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
use App\Entity\Tag;
use App\Utils\CollationKey;
use App\Utils\ObjectKey;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @implements DtoEntityIdentifierResolvableInterface<Tag>
 */
final class TagDto implements DependencyObjectInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DtoEntityIdentifierResolvableInterface
{
    public ?int $entityId = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public ?string $name = null;

    public static function fromEntity(Tag $tag): self
    {
        $dto = new self();
        $dto->entityId = $tag->getId();
        $dto->name = $tag->getName();

        return $dto;
    }

    public static function fromString(string $name): self
    {
        $dto = new self();
        $dto->name = trim($name);

        return $dto;
    }

    public function getKeyPrefix(): string
    {
        return Tag::KEY_PREFIX;
    }

    public function getUniqueKey(): string
    {
        if (null === $this->name || '' === trim($this->name)) {
            return ObjectKey::transient($this->getKeyPrefix(), $this);
        }

        // The key of Tag::getUniqueKey(), which the provider files tags under: equal for the
        // names the unique index on tag.name holds equal, see CollationKey
        return ObjectKey::data($this->getKeyPrefix(), CollationKey::of(trim($this->name)));
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return ObjectKey::internal($this->getKeyPrefix(), $this->entityId);
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        $this->entityId = $entity->getId();
    }
}
