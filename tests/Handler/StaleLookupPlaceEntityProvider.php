<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Contracts\BatchResetInterface;
use App\Contracts\EntityProviderInterface;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\EntityProvider\PlaceEntityProvider;

/**
 * Test double for the two-worker race: once armed, the next place lookups (the
 * external-id pass and the eager pass of one chunk) miss on purpose, as they do when
 * another worker has not committed its rows yet. It disarms itself after that, so the
 * re-run of the batch sees the real rows.
 *
 * @implements EntityProviderInterface<PlaceDto, Place>
 */
final class StaleLookupPlaceEntityProvider implements EntityProviderInterface, BatchResetInterface
{
    private bool $armed = false;

    public function __construct(private readonly PlaceEntityProvider $inner)
    {
    }

    public function missNextLookups(): void
    {
        $this->armed = true;
    }

    public function supports(string $dtoClassName): bool
    {
        return $this->inner->supports($dtoClassName);
    }

    public function prefetchEntities(array $dtos, bool $eager): void
    {
        if ($this->armed) {
            // The eager pass is the last lookup of a chunk
            if ($eager) {
                $this->armed = false;
            }

            return;
        }

        $this->inner->prefetchEntities($dtos, $eager);
    }

    public function getEntity(object $dto): ?object
    {
        return $this->inner->getEntity($dto);
    }

    public function addEntity(object $entity, ?object $fromDto = null): void
    {
        $this->inner->addEntity($entity, $fromDto);
    }

    public function clear(): void
    {
        $this->inner->clear();
    }

    public function batchReset(): void
    {
        $this->inner->batchReset();
    }
}
