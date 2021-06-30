<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

trait DtoExternalDateFilterableTrait
{
    /** @var \DateTimeInterface|null */
    public $externalUpdatedAt;

    public function getExternalUpdatedAt(): ?\DateTimeInterface
    {
        return $this->externalUpdatedAt;
    }
}
