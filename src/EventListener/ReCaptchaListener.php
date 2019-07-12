<?php

namespace App\EventListener;

use FOS\UserBundle\Controller\RegistrationController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ReCaptchaListener implements EventSubscriberInterface
{
    private $formName;
    private $field;

    public function __construct($formName, $field)
    {
        $this->formName = $formName;
        $this->field = $field;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $controller = $event->getController();

        if (!\is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof RegistrationController) {
            $request = $event->getRequest()->request;

            $captchaResponse = $request->get('g-recaptcha-response');
            if ($captchaResponse) {
                $formData = $request->get($this->formName);
                $formData[$this->field] = $captchaResponse;
                $request->set($this->formName, $formData);
                $request->remove('g-recaptcha-response');
            }
        }
    }
}
