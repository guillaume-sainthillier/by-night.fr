<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\EventExport;
use App\Enum\TransferFormat;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A member's export of events, pending.
 *
 * @extends PersistentObjectFactory<EventExport>
 */
final class EventExportFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EventExport::class;
    }

    /**
     * The export of the member's publisher, as the export page starts it.
     */
    protected function initialize(): static
    {
        return $this->beforeInstantiate(static fn (array $parameters, string $class, self $factory): array => PublisherFactory::forTheMember($parameters, $factory->isPersisting()));
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'format' => TransferFormat::Csv,
        ];
    }
}
