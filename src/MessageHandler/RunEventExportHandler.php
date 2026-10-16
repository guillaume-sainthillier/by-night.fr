<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\RunEventExport;
use App\Repository\EventExportRepository;
use App\Transfer\Export\EventExporter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RunEventExportHandler
{
    public function __construct(
        private EventExportRepository $exportRepository,
        private EventExporter $exporter,
    ) {
    }

    public function __invoke(RunEventExport $message): void
    {
        $export = $this->exportRepository->find($message->exportId);
        if (null !== $export) {
            $this->exporter->export($export);
        }
    }
}
