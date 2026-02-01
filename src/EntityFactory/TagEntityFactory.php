<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\TagDto;
use App\Entity\Tag;
use App\Exception\UncreatableEntityException;

/**
 * @implements EntityFactoryInterface<TagDto, Tag>
 */
final readonly class TagEntityFactory implements EntityFactoryInterface
{
    public function supports(string $dtoClassName): bool
    {
        return TagDto::class === $dtoClassName;
    }

    public function create(?object $entity, object $dto): object
    {
        // Create new tag via repository (handles caching)
        if (null === $dto->name || '' === trim($dto->name)) {
            throw new UncreatableEntityException('Tag has no name');
        }

        $entity ??= new Tag();
        $entity->setName($dto->name);

        return $entity;
    }
}
