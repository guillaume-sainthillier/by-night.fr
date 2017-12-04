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
use App\Social\FacebookAdmin;
use App\Social\Social;
use App\Social\Twitter;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/_internal")
 */
class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    private $socials;

    public function __construct(FacebookAdmin $facebookAdmin, Twitter $twitter)
    {
        $this->socials = [
            'facebook' => $facebookAdmin,
            'twitter'  => $twitter,
        ];
    }

    /**
     * @Route("/header/{city}", name="tbn_private_header_site")
     * @Route("/header", name="tbn_private_header")
     */
    public function headerAction(City $city = null)
    {
        $response = $this->render('menu.html.twig', [
            'city' => $city,
        ]);

        $tomorrow = new \DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }

    public function footerAction()
    {
        $cache  = $this->get('memory_cache');
        $params = [];
        foreach ($this->socials as $name => $service) {
            /**
             * @var Social
             */
            $key = 'app.social_counts.' . $name;
            if (!$cache->contains($key)) {
                $cache->save($key, $service->getNumberOfCount(), self::LIFE_TIME_CACHE);
            }

            $params['count_' . $name] = $cache->fetch($key);
        }

        $repo             = $this->getDoctrine()->getRepository(City::class);
        $params['cities'] = $repo->findRandomNames();
        $response         = $this->render('City/footer.html.twig', $params);

        $tomorrow = new \DateTime('tomorrow');

        return $response
            ->setExpires($tomorrow)
            ->setSharedMaxAge($this->getSecondsUntilTomorrow());
    }
}
