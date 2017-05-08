<?php

/*
 * This file is part of the FOSHttpCacheBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Configuration;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;


/**
 * @Annotation
 */
class BrowserCache extends ConfigurationAnnotation
{
    private $useCache = true;

    public function setValue($useCache)
    {
        $this->useCache = $useCache;
    }

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
