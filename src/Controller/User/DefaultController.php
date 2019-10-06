<?php

namespace App\Controller\User;

use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/membres")
 */
class DefaultController extends BaseController
{
    public function urlRedirectAction($term)
    {
        $params = [
            'type' => 'membres',
        ];

        if ($term) {
            $params['q'] = $term;
        }

        return new RedirectResponse($this->generateUrl('app_search_query', $params));
    }

    protected function checkUserUrl($slug, $username, $id, $routeName, array $extraParams = [])
    {
        $em = $this->getDoctrine()->getManager();
        $repoUser = $em->getRepository(User::class);

        if (!$id) {
            $user = $repoUser->findOneBy(['username' => $username]);
        } else {
            $user = $repoUser->find($id);
        }

        if (!$user || !$user->getSlug()) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getSlug() !== $slug) {
            $routeParams = \array_merge(['id' => $user->getId(), 'slug' => $user->getSlug()], $extraParams);

            return new RedirectResponse($this->generateUrl($routeName, $routeParams));
        }

        return $user;
    }

    /**
     * @Route("/{slug}--{id}", name="app_user_details", requirements={"slug": "[^/]+", "id": "\d+"})
     * @Route("/{username}", name="app_user_details_old", requirements={"username": "[^/]+"})
     *
     * @param null $id
     * @param null $slug
     * @param null $username
     *
     * @return object|RedirectResponse|Response|null
     */
    public function indexAction($id = null, $slug = null, $username = null)
    {
        $result = $this->checkUserUrl($slug, $username, $id, 'app_user_details');
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        return $this->render('User/index.html.twig', [
            'user' => $user,
            'next_events' => $repo->findAllNextEvents($user),
            'previous_events' => $repo->findAllNextEvents($user, false),
            'etablissements' => $repo->findAllPlaces($user),
            'count_favoris' => $repo->getCountParticipations($user) + $repo->getCountInterets($user),
        ]);
    }

    /**
     * @Route("/{slug}--{id}/stats/{type}", name="app_user_stats", requirements={"slug": "[^/]+", "id": "\d+", "type": "semaine|mois|annee"})
     * @Route("/{username}/stats/{type}", name="app_user_stats_old", requirements={"username": "[^/]+", "type": "semaine|mois|annee"})
     *
     * @param $type
     * @param null $id
     * @param null $slug
     * @param null $username
     *
     * @return object|JsonResponse|RedirectResponse|null
     */
    public function statsAction(Request $request, $type, $id = null, $slug = null, $username = null)
    {
        $result = $this->checkUserUrl($slug, $username, $id, 'app_user_stats', ['type' => $type]);
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result;

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);
        $str_date = $repo->getLastDateStatsUser($user);

        $response = $this->cacheVerif($str_date);
        if (null !== $response) {
            // Vérifie que l'objet Response n'est pas modifié
            // pour un objet Request donné
            if ($response->isNotModified($request)) {
                // Retourne immédiatement un objet 304 Response
                return $response;
            }
        }

        $datas = [];
        switch ($type) {
            case 'semaine':
                $datas = $this->getDataOfWeek($repo, $user);

                break;
            case 'mois':
                $datas = $this->getDataOfMonth($repo, $user);

                break;
            default:
            case 'annee':
                $datas = $this->getDataOfYear($repo, $user);

                break;
        }

        return $response->setData($datas);
    }

    protected function cacheVerif($str_date)
    {
        $response = new JsonResponse();

        if (null !== $str_date) {
            //2014-05-08 11:49:21
            if (($date = DateTime::createFromFormat('Y-m-d H:i:s', $str_date))) {
                $response->setPublic(); //Afin d'être partagée avec tout le monde
                $response->setLastModified($date);
            }
        }

        return $response;
    }

    protected function getDataOfWeek(EventRepository $repo, User $user)
    {
        $datas = $repo->getStatsUser($user, 'DAYOFWEEK');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        foreach (range(1, 7) as $day) {
            $date = new DateTime('0' . $day . '-01-' . date('Y'));
            $dayNumber = $date->format('w');
            $dateFormatter = \IntlDateFormatter::create(
                \Locale::getDefault(),
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE
            );

            $dateFormatter->setPattern('EEE');
            $final_datas['categories'][$dayNumber] = $dateFormatter->format($date);
            $dateFormatter->setPattern('EEEE');
            $final_datas['full_categories'][$dayNumber] = $dateFormatter->format($date);
        }

        ksort($final_datas['categories']);
        ksort($final_datas['full_categories']);

        return $this->fillDatas($final_datas, $datas);
    }

    protected function getDataOfMonth(EventRepository $repo, User $user)
    {
        $datas = $repo->getStatsUser($user, 'MONTH');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        foreach (range(1, 12) as $month) {
            $date = new DateTime('01-' . $month . '-' . date('Y'));
            $dateFormatter = \IntlDateFormatter::create(
                \Locale::getDefault(),
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE
            );

            $dateFormatter->setPattern('MMM');
            $final_datas['categories'][$month] = $dateFormatter->format($date);
            $dateFormatter->setPattern('MMMM');
            $final_datas['full_categories'][$month] = $dateFormatter->format($date);
        }

        return $this->fillDatas($final_datas, $datas);
    }

    protected function getDataOfYear(EventRepository $repo, User $user)
    {
        $datas = $repo->getStatsUser($user, 'YEAR');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        if (\count($datas)) {
            $minYear = min(array_keys($datas));
            $maxYear = max(array_keys($datas));
        } else {
            $minYear = (int) date('Y');
            $maxYear = (int) date('Y');
        }

        foreach (range($minYear, $maxYear) as $year) {
            $final_datas['categories'][$year] = $year;
            $final_datas['full_categories'][$year] = $year;
        }

        return $this->fillDatas($final_datas, $datas);
    }

    private function fillDatas(array $final_datas, array $datas)
    {
        foreach (array_keys($final_datas['categories']) as $key) {
            $final_datas['data'][$key] = $datas[$key] ?? 0;
        }

        return array_map('array_values', $final_datas);
    }
}
