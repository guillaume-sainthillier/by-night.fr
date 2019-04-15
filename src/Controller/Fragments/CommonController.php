<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 22:23.
 */

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Controller\TBNController;
use App\Entity\City;
use App\Entity\Country;
use App\Social\Social;
use App\Social\SocialProvider;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\Routing\Annotation\Route;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    /**
     * @Route("/_private/header/{id}", name="app_private_header", requirements={"id": "\d+"})
     * @ReverseProxy(expires="+1 day")
     */
    public function header(CityManager $cityManager, $id = null)
    {
        $city = null;
        if ($id) {
            $city = $this->getDoctrine()->getRepository(City::class)->find($id);
        }

        $city = $city ?: $cityManager->getCity();
        return $this->render('fragments/menu.html.twig', [
            'city' => $city,
        ]);
    }

    public function footer(Cache $memoryCache, SocialProvider $socialProvider, Country $country = null)
    {
        $socials = [
            'facebook' => $socialProvider->getSocial(SocialProvider::FACEBOOK_ADMIN),
            'twitter' => $socialProvider->getSocial(SocialProvider::TWITTER),
        ];

        $params = [];
        foreach ($socials as $name => $service) {
            /** @var Social $service */
            $key = 'app.social_counts.' . $name;
            if (!$memoryCache->contains($key)) {
                $memoryCache->save($key, $service->getNumberOfCount(), self::LIFE_TIME_CACHE);
            }

            $params['count_' . $name] = $memoryCache->fetch($key);
        }

        $repo = $this->getDoctrine()->getRepository(City::class);
        $params['cities'] = $repo->findRandomNames($country);

        return $this->render('fragments/footer.html.twig', $params);
    }
}
