<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 22:23
 */

namespace AppBundle\Controller\Fragments;

use AppBundle\Controller\TBNController;
use AppBundle\Entity\Site;
use AppBundle\Social\FacebookAdmin;
use Symfony\Component\Routing\Annotation\Route;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    /**
     * @Route("/header", name="tbn_private_header")
     */
    public function headerAction()
    {
        $response = $this->render('::menu.html.twig');

        $tomorrow = new \DateTime("tomorrow");
        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }

    public function footerAction(Site $site)
    {
        $cache = $this->get('memory_cache');
        $siteManager = $this->get('site_manager');

        $socials = [
            'facebook' => $this->get('tbn.social.facebook_admin'),
            'twitter' => $this->get('tbn.social.twitter'),
            'google' => $this->get('tbn.social.google')
        ];

        $params = [];
        foreach ($socials as $name => $social) {
            $key = 'tbn.counts.' . $name;
            if (!$cache->contains($key)) {
                if ($social instanceof FacebookAdmin) {
                    $social->setSiteInfo($siteManager->getSiteInfo());
                }
                $cache->save($key, $social->getNumberOfCount(), self::LIFE_TIME_CACHE);
            }

            $params['count_' . $name] = $cache->fetch($key);
        }

        $repo = $this->getDoctrine()->getRepository("AppBundle:Site");
        $params['sites'] = $repo->findRandomNames($site);
        $params['site'] = $site;
        $response = $this->render('City/footer.html.twig', $params);

        $tomorrow = new \DateTime("tomorrow");
        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }
}
