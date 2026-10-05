<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Import\SourceEventRemover;
use App\Message\RemoveSourceEvents;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveSourceEventsHandler
{
    public function __construct(
        private SourceEventRemover $sourceEventRemover,
    ) {
    }

    public function __invoke(RemoveSourceEvents $message): void
    {
        $this->sourceEventRemover->remove($message->externalOrigin, $message->externalIds, $message->sourcePrefix);
    }
}
