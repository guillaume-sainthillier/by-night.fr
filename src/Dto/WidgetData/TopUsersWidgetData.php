<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto\WidgetData;

final readonly class TopUsersWidgetData
{
    /**
     * @param array<\App\Entity\User> $users
     */
    public function __construct(
        public array $users,
        public ?string $hasNextLink,
        public int $current,
        public int $count,
    ) {
    }
}
