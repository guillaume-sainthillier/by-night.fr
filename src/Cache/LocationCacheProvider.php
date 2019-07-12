<?php

namespace App\Cache;

use App\Entity\Location;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class LocationCacheProvider extends CacheProvider
{
    /**
     * @var ObjectRepository
     */
    private $repo;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repo = $em->getRepository(Location::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        $location = $this->repo->find(\md5($id));

        if (null === $location) {
            return false;
        }

        return $location->getValues();
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return false !== $this->doFetch($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $location = new Location();
        $location
            ->setId(\md5($id))
            ->setName($id)
            ->setValues((array) $data);

        $this->em->persist($location);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        $location = $this->doFetch($id);
        if (false !== $location) {
            $this->em->remove($location);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        $this->em->flush();
        $this->em->clear(Location::class);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
    }
}
