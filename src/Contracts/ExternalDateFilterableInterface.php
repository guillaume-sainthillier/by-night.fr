<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

use DateTimeImmutable;

interface ExternalDateFilterableInterface extends ExternalIdentifiableInterface
{
    /**
     * Tells how to get the external updated date of an object (entity or dto).
     *
     * @return DateTimeImmutable the external last update date
     */
    public function getExternalUpdatedAt(): ?DateTimeImmutable;
}
