<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class ReverseProxy
{
    public function __construct(
        private ?int $ttl = null,
        private ?string $expires = null,
    ) {
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setTtl(?int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function getExpires(): ?string
    {
        return $this->expires;
    }

    public function setExpires(?string $expires): void
    {
        $this->expires = $expires;
    }

    public function getAliasName()
    {
        return 'reverse_proxy';
    }

    public function allowArray()
    {
        return false;
    }
}
