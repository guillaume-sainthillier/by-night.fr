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

    /**
     * @return int|null
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * @param int|null $ttl
     */
    public function setTtl(?int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * @return string|null
     */
    public function getExpires(): ?string
    {
        return $this->expires;
    }

    /**
     * @param string|null $expires
     */
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