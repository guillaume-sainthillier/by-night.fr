<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\ContentRemovalRequest;
use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ContentRemovalRequest>
 */
final class ContentRemovalRequestFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ContentRemovalRequest::class;
    }

    protected function defaults(): array
    {
        // Linked events are intentionally left out of the defaults: a request can target
        // an "image" or free-form URLs with no Event at all, and the tests that do need a
        // linked event pass it explicitly so they control the exact deletion under test.
        return [
            'email' => self::faker()->email(),
            'type' => self::faker()->randomElement(ContentRemovalType::cases()),
            'message' => self::faker()->paragraph(),
            'status' => ContentRemovalRequestStatus::Pending,
        ];
    }

    public function processed(?DateTimeImmutable $processedAt = null): self
    {
        return $this->with([
            'status' => ContentRemovalRequestStatus::Processed,
            'processedAt' => $processedAt ?? new DateTimeImmutable(),
        ]);
    }
}
