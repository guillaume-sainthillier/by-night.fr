<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\TagDto;
use App\Entity\Tag;
use App\Repository\TagRepository;

/**
 * @extends AbstractEntityProvider<TagDto, Tag>
 */
final class TagEntityProvider extends AbstractEntityProvider
{
    public function __construct(
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return TagDto::class === $dtoClassName;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->tagRepository;
    }
}
