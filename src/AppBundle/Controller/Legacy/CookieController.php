<?php

namespace AppBundle\Controller\Legacy;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class CookieController extends Controller
{
    /**
     * @Route("/cookie", name="tbn_main_cookie")
     */
    public function indexAction()
    {
        return $this->render('Cookie/index.html.twig');
    }
}
