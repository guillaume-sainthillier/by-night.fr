<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 31/05/2016
 * Time: 19:26.
 */

namespace AppBundle\Controller\Old\User;

use FOS\UserBundle\Controller\ProfileController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends BaseController
{
    /**
     * @Route("/{city}/login", name="fos_user_security_login_old", requirements={"city": ".+"})
     */
    public function loginAction()
    {
        return $this->redirectToRoute('fos_user_security_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/{city}/inscription", name="fos_user_registration_register_old", requirements={"city": ".+"})
     */
    public function registerAction()
    {
        return $this->redirectToRoute('fos_user_registration_register', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
