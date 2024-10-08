<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use Psr\Log\LoggerInterface;

abstract class AbstractConsumer
{
    public function __construct(protected LoggerInterface $logger)
    {
    }
}
