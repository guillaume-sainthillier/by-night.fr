<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface DtoConverterInterface extends SupportsObjectInterface
{
    /**
     * Convert a stdClass object from RabbitMQ feeders to the corresponding DTO instance.
     */
    public function convert(object $object): object;
}
