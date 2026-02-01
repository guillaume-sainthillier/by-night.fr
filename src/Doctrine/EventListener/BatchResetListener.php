<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Manager\BatchResetManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onClear)]
final readonly class BatchResetListener
{
    public function __construct(
        private BatchResetManager $batchResetManager,
    ) {
    }

    public function onClear(): void
    {
        $this->batchResetManager->resetServices();
    }
}
