<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class ReverseProxy extends ConfigurationAnnotation
{
    /**
     * @var int|null
     */
    private $ttl;

    /**
     * @var string|null
     */
    private $expires;

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

    /**
     * {@inheritdoc}
     */
    public function getAliasName()
    {
        return 'reverse_proxy';
    }

    /**
     * {@inheritdoc}
     */
    public function allowArray()
    {
        return false;
    }
}
