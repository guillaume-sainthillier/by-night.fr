<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\CheckEventImport;
use App\Repository\EventImportRepository;
use App\Transfer\Import\EventImportChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckEventImportHandler
{
    public function __construct(
        private EventImportRepository $importRepository,
        private EventImportChecker $checker,
    ) {
    }

    public function __invoke(CheckEventImport $message): void
    {
        $import = $this->importRepository->find($message->importId);
        // Deleted meanwhile (its member's account)
        if (null !== $import) {
            $this->checker->check($import);
        }
    }
}
