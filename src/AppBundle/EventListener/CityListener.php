<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 20:29
 */

namespace AppBundle\EventListener;


use AppBundle\Configuration\BrowserCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CityListener implements EventSubscriberInterface
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
        $request = $event->getRequest();

        if($request->attributes->has('_current_city') && $request->attributes->get('_current_city') !== $request->cookies->get('app_city')) {
            $cookie = new Cookie('app_city', $request->attributes->get('_current_city'), "+1 year");
            $event->getResponse()->headers->setCookie($cookie);
        }

        /**
         * @var BrowserCache|null $browserCache
         */
        $browserCache = $request->attributes->get('_browser_cache');
        if (null === $browserCache || $browserCache->hasToUseCache()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);
    }
}
