<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface PrefixableObjectKeyInterface
{
    /**
     * @return string the key prefix for object key
     */
    public function getKeyPrefix(): string;
}
