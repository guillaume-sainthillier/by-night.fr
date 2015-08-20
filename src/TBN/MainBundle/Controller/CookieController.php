<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CookieController extends Controller
{
    public function indexAction()
    {        
	return $this->render('TBNMainBundle:Cookie:index.html.twig');
    }
}
