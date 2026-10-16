<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\RunEventImport;
use App\Repository\EventImportRepository;
use App\Transfer\Import\EventImportRunner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RunEventImportHandler
{
    public function __construct(
        private EventImportRepository $importRepository,
        private EventImportRunner $runner,
    ) {
    }

    public function __invoke(RunEventImport $message): void
    {
        $import = $this->importRepository->find($message->importId);
        if (null !== $import) {
            $this->runner->run($import);
        }
    }
}
