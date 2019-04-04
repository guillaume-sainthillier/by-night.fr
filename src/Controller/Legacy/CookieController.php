<?php

namespace App\Controller\Legacy;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class CookieController extends AbstractController
{
    /**
     * @Route("/cookie", name="app_main_cookie")
     */
    public function indexAction()
    {
        return $this->render('Cookie/index.html.twig');
    }
}
