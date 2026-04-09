<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Contracts\BatchResetInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\Service\ResetInterface;

final readonly class BatchResetManager implements ResetInterface
{
    /**
     * @param iterable<string, BatchResetInterface> $batchResets
     */
    public function __construct(
        private iterable $batchResets,
    ) {
    }

    public function reset(): void
    {
        $this->resetServices();
    }

    public function resetServices(): void
    {
        foreach ($this->batchResets as $service) {
            if ($service instanceof LazyObjectInterface && !$service->isLazyObjectInitialized(true)) {
                continue;
            }

            $service->batchReset();
        }
    }
}
