<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Message;

/**
 * Tells the members going to these events what changed in them since they last heard of it, once the change has
 * settled (see OutingChangeListener, OutingChangeNotifier).
 */
final readonly class CheckOutingChanges
{
    /**
     * @param list<int> $eventIds
     */
    public function __construct(
        public array $eventIds,
    ) {
    }
}
