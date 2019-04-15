<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 22:23.
 */

namespace App\Controller\Fragments;

use App\Controller\TBNController;
use App\Entity\City;
use App\Entity\Country;
use App\Social\Social;
use App\Social\SocialProvider;
use DateTime;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/_internal")
 */
class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    /**
     * @Route("/header/{slug}", name="app_private_header_site")
     * @Route("/header", name="app_private_header")
     */
    public function header(City $city = null)
    {
        $response = $this->render('fragments/menu.html.twig', [
            'city' => $city,
        ]);

        $tomorrow = new DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
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
        $response = $this->render('fragments/footer.html.twig', $params);

        $tomorrow = new DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }
}
