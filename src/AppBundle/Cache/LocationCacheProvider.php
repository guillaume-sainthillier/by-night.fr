<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 17/05/2017
 * Time: 21:01.
 */

namespace AppBundle\Cache;

use AppBundle\Entity\Location;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\EntityManagerInterface;

class LocationCacheProvider extends CacheProvider
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository
     */
    private $repo;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em   = $em;
        $this->repo = $em->getRepository('AppBundle:Location');
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        $location = $this->repo->find($id);

        if (!$location) {
            return false;
        }

        return $location->getValues();
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return $this->doFetch($id) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $location = new Location();
        $location
            ->setId($id)
            ->setValues((array) $data);

        $this->em->persist($location);
        $this->em->flush();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        $location = $this->repo->find($id);
        if ($location) {
            $this->em->remove($location);
            $this->em->flush();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        $this->em->clear(Location::class);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        return;
    }
}
