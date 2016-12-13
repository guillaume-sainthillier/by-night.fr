<?php

namespace TBN\MajDataBundle\Cleaner;
use Doctrine\ORM\EntityManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 23/11/2016
 * Time: 21:11
 */
class ImageCleaner
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    private $webDir;

    public function __construct(EntityManager $entityManager, CacheManager $cacheManager, Filesystem $filesystem, $webDir) {
        $this->entityManager = $entityManager;
        $this->webDir = $webDir;
        $this->filesystem = $filesystem;
        $this->cacheManager = $cacheManager;
    }

    public function clean() {
        $result = $this
            ->entityManager
            ->createQuery("SELECT a.path FROM TBNAgendaBundle:Agenda a WHERE a.path IS NOT NULL")
            ->getScalarResult();

        $paths = array_filter(array_column($result, "path"));
        $this->cleanPaths($paths, ['thumbs_evenement', 'thumb_evenement'], '/uploads/documents');

        $result = $this
            ->entityManager
            ->createQuery("SELECT u.path FROM TBNUserBundle:User u WHERE u.path IS NOT NULL")
            ->getScalarResult();

        $paths = array_filter(array_column($result, "path"));
        $this->cleanPaths($paths, ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'], '/uploads/users');
    }

    protected function cleanPaths(array $paths, array $filters, $uri_prefix) {
        $finder = new Finder();
        $files = $finder->in($this->webDir . $uri_prefix);
        foreach($files as $file) {
            if(! $file->getFilename()) {
                continue;
            }

            if(! in_array($file->getFilename(), $paths)) {
                $path = $uri_prefix . '/'.$file->getFilename();
                foreach($filters as $filter) {
                    if($this->cacheManager->isStored($path, $filter)) {
                        $this->cacheManager->remove($path, $filter);
                    }
                }
            }
        }
    }
}