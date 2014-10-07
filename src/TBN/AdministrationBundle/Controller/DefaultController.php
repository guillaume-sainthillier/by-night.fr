<?php

namespace TBN\AdministrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('TBNAdministrationBundle:Default:index.html.twig', ['name' => $name]);
    }
}
