<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

trait DtoExternalIdentifiableTrait
{
    public ?string $externalId = null;

    public ?string $externalOrigin = null;

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getExternalOrigin(): ?string
    {
        return $this->externalOrigin;
    }
}
