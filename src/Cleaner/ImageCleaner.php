<?php

namespace App\Cleaner;

use Doctrine\Common\Persistence\ObjectManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
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
     * @var ObjectManager
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

    public function __construct(ObjectManager $entityManager, CacheManager $cacheManager, $webDir)
    {
        $this->entityManager = $entityManager;
        $this->webDir        = $webDir;
        $this->cacheManager  = $cacheManager;
    }

    public function clean()
    {
        $result = $this
            ->entityManager
            ->createQuery('SELECT a.path, a.systemPath FROM App:Agenda a WHERE a.path IS NOT NULL OR a.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumbs_evenement', 'thumb_evenement'], '/uploads/documents');

        $result = $this
            ->entityManager
            ->createQuery('SELECT u.path, u.systemPath FROM App:User u WHERE u.path IS NOT NULL OR u.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'], '/uploads/users');
    }

    protected function cleanPaths(array $paths, array $filters, $uri_prefix)
    {
        $fs = new Filesystem();
        $finder = new Finder();
        $files  = $finder->files()->in($this->webDir . $uri_prefix);
        foreach ($files as $file) {
            /** @var \SplFileObject $file */
            if (\in_array($file->getFilename(), $paths)) {
                continue;
            }

            //Orphan file
            $path = ltrim(str_replace($this->webDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            foreach ($filters as $filter) {
                if ($this->cacheManager->isStored($path, $filter)) {
                    $this->cacheManager->remove($path, $filter);
                }
            }

            $fs->remove($file->getPathname());
        }
    }
}
