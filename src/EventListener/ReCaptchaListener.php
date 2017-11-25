<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 23:28.
 */

namespace AppBundle\EventListener;

use FOS\UserBundle\Controller\RegistrationController;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

class ReCaptchaListener
{
    private $formName;
    private $field;

    public function __construct($formName, $field)
    {
        $this->formName = $formName;
        $this->field    = $field;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof RegistrationController) {
            $request = $event->getRequest()->request;

            $captchaResponse = $request->get('g-recaptcha-response');
            if ($captchaResponse) {
                $formData               = $request->get($this->formName);
                $formData[$this->field] = $captchaResponse;
                $request->set($this->formName, $formData);
                $request->remove('g-recaptcha-response');
            }
        }
    }
}
