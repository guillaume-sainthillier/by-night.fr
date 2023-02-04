<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use DateTimeInterface;

trait DtoExternalDateFilterableTrait
{
    public ?DateTimeInterface $externalUpdatedAt = null;

    public function getExternalUpdatedAt(): ?DateTimeInterface
    {
        return $this->externalUpdatedAt;
    }
}
