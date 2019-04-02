<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 31/05/2016
 * Time: 19:26.
 */

namespace App\Controller\Old\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    /**
     * @Route("/{city}/login", requirements={"city": ".+"})
     */
    public function loginAction()
    {
        return $this->redirectToRoute('fos_user_security_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/{city}/inscription", requirements={"city": ".+"})
     */
    public function registerAction()
    {
        return $this->redirectToRoute('fos_user_registration_register', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
