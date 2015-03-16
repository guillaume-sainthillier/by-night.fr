<?php

namespace TBN\UserBundle\EventListener\RegistrationConfirmListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class RegistrationConfirmListener implements EventSubscriberInterface {

    private $router;

    public function __construct(UrlGeneratorInterface $router) {
        $this->router = $router;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents() {
        return [
            FOSUserEvents::REGISTRATION_CONFIRM => 'onRegistrationConfirm'
        ];
    }

    public function onRegistrationConfirm(GetResponseUserEvent $event) {
        $url = $this->router->generate('rsWelcomeBundle_check_full_register');

        $event->setResponse(new RedirectResponse($url));
    }

}
