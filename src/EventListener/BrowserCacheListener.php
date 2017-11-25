<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 20:29.
 */

namespace AppBundle\EventListener;

use AppBundle\Configuration\BrowserCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BrowserCacheListener implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if ($event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        /**
         * @var BrowserCache|null
         */
        $browserCache = $request->attributes->get('_browser_cache');
        if (null === $browserCache || $browserCache->hasToUseCache()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->add([
            'X-No-Browser-Cache' => '1',
        ]);
    }
}
