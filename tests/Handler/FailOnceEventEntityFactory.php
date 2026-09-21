<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Handler;

use App\Contracts\EntityFactoryInterface;
use App\Dto\EventDto;
use App\Entity\Event;
use App\EntityFactory\EventEntityFactory;
use RuntimeException;

/**
 * Test double: fails the first event it is asked to create, then behaves normally.
 * Simulates an exception raised in the middle of an import batch, after the
 * explorations have been flushed but before the events are merged.
 *
 * @implements EntityFactoryInterface<EventDto, Event>
 */
final class FailOnceEventEntityFactory implements EntityFactoryInterface
{
    public const string FAILURE_MESSAGE = 'Simulated failure while merging the event';

    private bool $failed = false;

    public function __construct(private readonly EventEntityFactory $inner)
    {
    }

    public function supports(string $dtoClassName): bool
    {
        return $this->inner->supports($dtoClassName);
    }

    /**
     * @param Event|null $entity
     * @param EventDto   $dto
     */
    public function create(?object $entity, object $dto): object
    {
        if (!$this->failed) {
            $this->failed = true;

            throw new RuntimeException(self::FAILURE_MESSAGE);
        }

        return $this->inner->create($entity, $dto);
    }
}
