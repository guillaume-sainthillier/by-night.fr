<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 22:23
 */

namespace TBN\MainBundle\Controller;

use TBN\SocialBundle\Social\FacebookAdmin;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    public function headerAction() {
        return $this->render('::menu.html.twig');
    }

    public function footerAction() {
        $cache = $this->get('memory_cache');
        $siteManager = $this->get('site_manager');
        $currentSite = $siteManager->getCurrentSite();

        $socials = [
            'facebook' => $this->get('tbn.social.facebook_admin'),
            'twitter' => $this->get('tbn.social.twitter'),
            'google' => $this->get('tbn.social.google')
        ];

        $params = [];
        foreach ($socials as $name => $social) {
            $key = 'tbn.counts.' . $name;
            if (!$cache->contains($key)) {
                if($social instanceof FacebookAdmin) {
                    $social->setSiteInfo($siteManager->getSiteInfo());
                }
                $cache->save($key, $social->getNumberOfCount(), self::LIFE_TIME_CACHE);
            }

            $params['count_' . $name] = $cache->fetch($key);
        }

        $repo = $this->getDoctrine()->getRepository("TBNMainBundle:Site");
        $params['sites'] = $repo->findRandomNames($currentSite);

        return $this->render('TBNAgendaBundle::footer.html.twig', $params);
    }
}