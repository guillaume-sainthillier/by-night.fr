<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 31/05/2016
 * Time: 19:26
 */

namespace AppBundle\Controller\Old\User;

use AppBundle\Entity\Agenda;
use AppBundle\Entity\Calendrier;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use \FOS\UserBundle\Controller\ProfileController as BaseController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends BaseController
{
    /**
     * @Route("/{city}/login", name="fos_user_security_login_old", requirements={"city": ".+"})
     */
    public function loginAction() {
        return $this->redirectToRoute("fos_user_security_login");
    }

    /**
     * @Route("/{city}/inscription", name="fos_user_registration_register_old", requirements={"city": ".+"})
     */
    public function registerAction() {
        return $this->redirectToRoute("fos_user_registration_register");
    }
}
