<?php

namespace TBN\AdministrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use TBN\UserBundle\Entity\SiteInfo;

class InfoController extends Controller
{
    public function listAction()
    {
        $repo = $this->getDoctrine()->getRepository("TBNUserBundle:SiteInfo");
        $info = $repo->findOneBy([]);
        
        if($info === null)
        {
            $info   = new SiteInfo;
            $em     = $this->getDoctrine()->getManager();
            $em->persist($info);
            $em->flush();
        }
        
        return $this->redirect($this->get("router")->generate("tbn_administration_info_edit", ["id" => $info->getId()]));
    }

    public function connectInfoAction($service)
    {
        $session = $this->container->get("session");
        $session->set("connect_site",true);

        $url = $this->get("router")->generate("hwi_oauth_service_redirect", ["service" => $service === "facebook" ? "facebook_admin" : $service]);
        return $this->redirect($url);
    }

    public function editAction(SiteInfo $info)
    {
        return $this->render('TBNAdministrationBundle:Info:edit.html.twig', [
            'info' => $info
        ]);
    }
}
