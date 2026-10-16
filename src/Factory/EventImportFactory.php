<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\EventImport;
use App\Enum\TransferFormat;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A member's import of events, as uploaded (its file is not written: put one in transfers.storage when it matters).
 *
 * @extends PersistentObjectFactory<EventImport>
 */
final class EventImportFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EventImport::class;
    }

    /**
     * The counters have no setter (only the check and the import count): a test sets them as it needs.
     */
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor()->alwaysForce());
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'format' => TransferFormat::Csv,
            'originalName' => 'evenements.csv',
            'filePath' => 'imports/' . self::faker()->uuid() . '.csv',
        ];
    }
}
