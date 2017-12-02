<?php

namespace AppBundle\Configuration;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class BrowserCache extends ConfigurationAnnotation
{
    /**
     * @var bool
     */
    private $useCache = true;

    /**
     * @param bool $useCache
     */
    public function setValue($useCache)
    {
        $this->useCache = $useCache;
    }

    /**
     * @return bool
     */
    public function hasToUseCache()
    {
        return $this->useCache;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliasName()
    {
        return 'browser_cache';
    }

    /**
     * {@inheritdoc}
     */
    public function allowArray()
    {
        return false;
    }
}
