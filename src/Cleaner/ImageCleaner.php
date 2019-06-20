<?php

namespace App\Cleaner;

use App\Utils\Monitor;
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
        $this->webDir = $webDir;
        $this->cacheManager = $cacheManager;
    }

    public function clean(bool $dry_run = false)
    {
        $result = $this
            ->entityManager
            ->createQuery('SELECT a.path, a.systemPath FROM App:Event a WHERE a.path IS NOT NULL OR a.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumbs_evenement', 'thumb_evenement'], '/uploads/documents', $dry_run);

        $result = $this
            ->entityManager
            ->createQuery('SELECT u.path, u.systemPath FROM App:User u WHERE u.path IS NOT NULL OR u.systemPath IS NOT NULL')
            ->getScalarResult();

        $paths = \array_unique(\array_filter(\array_merge(\array_column($result, 'path'), \array_column($result, 'systemPath'))));
        $this->cleanPaths($paths, ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'], '/uploads/users', $dry_run);
    }

    protected function cleanPaths(array $paths, array $filters, string $uri_prefix, bool $dry_run)
    {
        $fs = new Filesystem();
        $finder = new Finder();
        $files = $finder->files()->in($this->webDir . $uri_prefix);
        foreach ($files as $file) {
            /** @var \SplFileObject $file */
            if (\in_array($file->getFilename(), $paths)) {
                continue;
            }

            if ($dry_run) {
                Monitor::writeln(sprintf('Suppression de %s', $file->getPathname()));
            }

            //Orphan file
            $path = ltrim(str_replace($this->webDir, '', $file->getPathname()), \DIRECTORY_SEPARATOR);
            foreach ($filters as $filter) {
                if ($this->cacheManager->isStored($path, $filter)) {
                    if ($dry_run) {
                        Monitor::writeln(sprintf("\tSuppression du filtre %s : %s", $filter, $path));
                    } else {
                        $this->cacheManager->remove($path, $filter);
                    }
                }
            }

            if (!$dry_run) {
                $fs->remove($file->getPathname());
            }
        }
    }
}
