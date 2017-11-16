<?php

namespace TBN\UserBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\UserBundle\Entity\User;

class UserController extends Controller
{
    public function urlRedirectAction($term)
    {
        $params = [
            'type' => 'membres',
        ];

        if ($term) {
            $params['q'] = $term;
        }

        return new RedirectResponse($this->get('router')->generate('tbn_search_query', $params));
    }

    protected function checkUserUrl($slug, $username, $id, $routeName, array $extraParams = [])
    {
        $em       = $this->getDoctrine()->getManager();
        $repoUser = $em->getRepository('TBNUserBundle:User');

        if (!$id) {
            $user = $repoUser->findOneBy(['username' => $username]);
        } else {
            $user = $repoUser->find($id);
        }

        if (!$user || !$user->getSlug()) {
            throw new NotFoundHttpException('User not found');
        }

        if ($user->getSlug() !== $slug) {
            $routeParams = array_merge(['id' => $user->getId(), 'slug' => $user->getSlug()], $extraParams);

            return new RedirectResponse($this->generateUrl($routeName, $routeParams));
        }

        return $user;
    }

    public function detailsAction($id = null, $slug = null, $username = null)
    {
        $result = $this->checkUserUrl($slug, $username, $id, 'tbn_user_details');
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result;

        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        $siteManager = $this->get('site_manager');
        $currentSite = $siteManager->getCurrentSite();
        if ($currentSite && $user->getSite() !== $currentSite) {
            return new RedirectResponse($this->generateUrl('tbn_user_details', [
                'slug'      => $user->getSlug(),
                'id'        => $user->getId(),
                'subdomain' => $user->getSite()->getSubdomain(),
            ]));
        }

        return $this->render('TBNUserBundle:Membres:details.html.twig', [
            'user'                 => $user,
            'next_events'          => $repo->findAllNextEvents($user),
            'previous_events'      => $repo->findAllNextEvents($user, false),
            'etablissements'       => $repo->findAllPlaces($user),
            'count_participations' => $repo->getCountParticipations($user),
            'count_interets'       => $repo->getCountInterets($user),
        ]);
    }

    public function statsAction(Request $request, $type, $id = null, $slug = null, $username = null)
    {
        $result = $this->checkUserUrl($slug, $username, $id, 'tbn_user_stats', ['type' => $type]);
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result;

        $em       = $this->getDoctrine()->getManager();
        $repo     = $em->getRepository('TBNAgendaBundle:Agenda');
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
            if (($date = \DateTime::createFromFormat('Y-m-d H:i:s', $str_date))) {
                $response->setPublic(); //Afin d'être partagée avec tout le monde
                $response->setLastModified($date);
            }
        }

        return $response;
    }

    protected function getDataOfWeek($repo, User $user)
    {
        $now   = new \DateTime();
        $date  = $this->calculDate('P1W');
        $datas = $repo->getStatsUser($user, $date);

        $final_datas = [
            'categories' => [],
            'data'       => [],
            'full_categories',
        ];

        for ($i = 1; $date <= $now; ++$i) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data['date_event'] == $date->format('m-d')) {
                    $nb_events = $data['nbEvents'];
                }
            }

            $cle                              = ucfirst($this->getDayName($date->format('N')));
            $final_datas['full_categories'][] = $cle.' '.$date->format('d').' '.$this->getMonthName($date->format('m')).' '.$date->format('Y');
            $final_datas['categories'][]      = $cle;
            $final_datas['data'][]            = intval($nb_events);

            $date->add(new \DateInterval('P1D'));
        }

        return $final_datas;
    }

    protected function getDataOfMonth($repo, User $user)
    {
        $now   = new \DateTime();
        $date  = $this->calculDate('P1M');
        $datas = $repo->getStatsUser($user, $date);

        $final_datas = [
            'categories' => [],
            'data'       => [],
            'full_categories',
        ];

        for ($i = 1; $date <= $now; ++$i) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data['date_event'] == $date->format('m-d')) {
                    $nb_events = $data['nbEvents'];
                }
            }

            $cle = ucfirst($this->getDayName($date->format('N'))).' '.$date->format('d');

            $final_datas['full_categories'][] = ucfirst($this->getDayName($date->format('N'))).' '.$date->format('d').' '.$this->getMonthName($date->format('m')).' '.$date->format('Y');
            $final_datas['categories'][]      = $cle;
            $final_datas['data'][]            = intval($nb_events);

            $date->add(new \DateInterval('P1D'));
        }

        return $final_datas;
    }

    protected function getDataOfYear($repo, User $user)
    {
        $now   = new \DateTime();
        $date  = $this->calculDate('P1Y');
        $datas = $repo->getStatsUser($user, $date, false);

        $final_datas = [
            'categories' => [],
            'data'       => [],
            'full_categories',
        ];

        for ($i = 1; $date <= $now; ++$i) {
            $nb_events = 0;
            foreach ($datas as $data) {
                if ($data['date_event'] == $date->format('Y-m')) {
                    $nb_events = $data['nbEvents'];
                }
            }

            $cle                              = ucfirst(utf8_encode(substr(utf8_decode($this->getMonthName($date->format('m'))), 0, 3)));
            $final_datas['full_categories'][] = ucfirst($this->getMonthName($date->format('m'))).' '.$date->format('Y');
            $final_datas['categories'][]      = $cle;
            $final_datas['data'][]            = intval($nb_events);

            $date->add(new \DateInterval('P1M'));
        }

        return $final_datas;
    }

    protected function getMonthName($number)
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(intval($number) - 1)];
    }

    protected function getDayName($number)
    {
        $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        return $days[(intval($number) - 1)];
    }

    protected function calculDate($format)
    {
        $debut = new \DateTime();
        $debut->sub(new \DateInterval($format));

        return $debut;
    }
}
