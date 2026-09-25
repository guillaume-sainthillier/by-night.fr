<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface PrefixableObjectKeyInterface
{
    /**
     * The prefix of the object's keys (see ObjectKey): the entity's KEY_PREFIX, for the
     * entity and its DTO alike.
     */
    public function getKeyPrefix(): string;
}
