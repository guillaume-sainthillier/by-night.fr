<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

interface ExternalIdentifiableInterface
{
    /**
     * Tells how to get the external id of an object (entity or dto).
     *
     * @return string the external id of object
     */
    public function getExternalId(): ?string;

    /**
     * Tells how to get the external source of an object (entity or dto).
     *
     * @return string the external source of object
     */
    public function getExternalOrigin(): ?string;
}
