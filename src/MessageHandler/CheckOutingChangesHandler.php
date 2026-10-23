<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\CheckOutingChanges;
use App\Outing\OutingChangeNotifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckOutingChangesHandler
{
    public function __construct(
        private OutingChangeNotifier $notifier,
    ) {
    }

    public function __invoke(CheckOutingChanges $message): void
    {
        $this->notifier->notify($message->eventIds);
    }
}
