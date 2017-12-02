<?php

namespace AppBundle\Cleaner;

use Doctrine\ORM\EntityManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Finder\Finder;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/11/2016
 * Time: 21:11.
 */
class ImageCleaner
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var string
     */
    private $webDir;

    public function __construct(EntityManager $entityManager, CacheManager $cacheManager, $webDir)
    {
        $this->entityManager = $entityManager;
        $this->webDir        = $webDir;
        $this->cacheManager  = $cacheManager;
    }

    public function clean()
    {
        $result = $this
            ->entityManager
            ->createQuery('SELECT a.path, a.systemPath FROM AppBundle:Agenda a WHERE a.path IS NOT NULL OR a.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumbs_evenement', 'thumb_evenement'], '/uploads/documents');

        $result = $this
            ->entityManager
            ->createQuery('SELECT u.path, u.systemPath FROM AppBundle:User u WHERE u.path IS NOT NULL OR u.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'], '/uploads/users');
    }

    protected function cleanPaths(array $paths, array $filters, $uri_prefix)
    {
        $finder = new Finder();
        $files  = $finder->in($this->webDir . $uri_prefix);
        foreach ($files as $file) {
            /*
             * @var \SplFileObject
             */
            if (!$file->getFilename()) {
                continue;
            }

            if (!\in_array($file->getFilename(), $paths)) {
                $path = $uri_prefix . '/' . $file->getFilename();
                foreach ($filters as $filter) {
                    if ($this->cacheManager->isStored($path, $filter)) {
                        $this->cacheManager->remove($path, $filter);
                    }
                }
            }
        }
    }
}
