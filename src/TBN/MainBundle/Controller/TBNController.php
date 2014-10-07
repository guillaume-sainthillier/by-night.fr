<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TBNController extends Controller
{

    public function getRepo($name)
    {
        $em = $this->getDoctrine()->getManager();
        return $em->getRepository($name);
    }
}
