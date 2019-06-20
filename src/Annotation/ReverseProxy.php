<?php

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
