<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 22:23.
 */

namespace App\Controller\Fragments;

use App\App\CityManager;
use App\Controller\TBNController;
use App\Entity\City;
use App\Social\FacebookAdmin;
use App\Social\Social;
use App\Social\Twitter;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/_internal")
 */
class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    /**
     * @Route("/header/{city}", name="tbn_private_header_site")
     * @Route("/header", name="tbn_private_header")
     *
     * @param City|null $city
     *
     * @return Response
     */
    public function header(City $city = null)
    {
        $response = $this->render('menu.html.twig', [
            'city' => $city,
        ]);

        $tomorrow = new DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }

    public function footer(CityManager $cityManager, FacebookAdmin $facebookAdmin, Twitter $twitter)
    {
        $cache = $this->get('memory_cache');
        $socials = [
            'facebook' => $facebookAdmin,
            'twitter' => $twitter
        ];
        $params = [];
        foreach ($socials as $name => $service) {
            /** @var Social $service */
            $key = 'app.social_counts.' . $name;
            if (!$cache->contains($key)) {
                $cache->save($key, $service->getNumberOfCount(), self::LIFE_TIME_CACHE);
            }

            $params['count_' . $name] = $cache->fetch($key);
        }

        $repo = $this->getDoctrine()->getRepository(City::class);
        $params['cities'] = $repo->findRandomNames($cityManager->getCity() ? $cityManager->getCity()->getCountry() : null);
        $response = $this->render('City/footer.html.twig', $params);

        $tomorrow = new DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }
}
