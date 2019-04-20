<?php

namespace App\EventListener;

use App\Entity\Agenda;
use App\Entity\Site;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

class ImageListener implements EventSubscriberInterface
{
    private $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_REMOVE => 'onImageDelete',
        ];
    }

    public function onImageDelete(Event $event)
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        if ($object instanceof User) {
            $filters = ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'];
        } elseif ($object instanceof Agenda) {
            $filters = ['thumbs_evenement', 'thumb_evenement'];
        } elseif ($object instanceof Site) {
            $filters = ['thumb_site', 'thumb_site_large'];
        } else {
            return;
        }

        $path = $mapping->getUriPrefix() . DIRECTORY_SEPARATOR . $mapping->getUploadDir($object) . DIRECTORY_SEPARATOR . $mapping->getFileName($object);

        foreach ($filters as $filter) {
            if ($this->cacheManager->isStored($path, $filter)) {
                $this->cacheManager->remove($path, $filter);
            }
        }
    }
}
