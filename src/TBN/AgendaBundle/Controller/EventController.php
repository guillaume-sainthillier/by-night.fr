<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Request;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Calendrier;

use TBN\AgendaBundle\Form\Type\SearchType;
use TBN\AgendaBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EventController extends Controller
{

    public function detailsAction(Request $request, Agenda $agenda)
    {
        $siteManager = $this->container->get('site_manager');
        $site = $siteManager->getCurrentSite();

        //Redirection vers le bon site
        if ($agenda->getSite() !== $site) {
            return new RedirectResponse($this->get('router')->generate('tbn_agenda_details', [
                'slug' => $agenda->getSlug(),
                'subdomain' => $agenda->getSite()->getSubdomain()
            ]));
        }

        return $this->render('TBNAgendaBundle:Agenda:details.html.twig', [
            'soiree' => $agenda,
            'stats' => $this->getAgendaStats($agenda, $request)
        ]);
    }

    protected function getAgendaStats(Agenda $agenda, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $participer = false;
        $interet = false;

        $user = $this->getUser();
        if ($user) {
            $repoCalendrier = $em->getRepository('TBNAgendaBundle:Calendrier');
            $calendrier = $repoCalendrier->findOneBy(['user' => $user, 'agenda' => $agenda]);
            if ($calendrier !== null) {
                $participer = $calendrier->getParticipe();
                $interet = $calendrier->getInteret();
            }

            $userInfo = $user->getInfo();
            if ($userInfo && $agenda->getFacebookEventId() && $userInfo->getFacebookId()) {
                $cache = $this->get('memory_cache');
                $key = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
                if (!$cache->contains($key)) {
                    $api = $this->get('tbn.social.facebook_admin');
                    $stats = $api->getUserEventStats($agenda->getFacebookEventId(), $userInfo->getFacebookId());
                    $cache->save($key, $stats);
                }
                $stats = $cache->fetch($key);

                if ($stats['participer'] || $stats['interet']) {
                    if (null === $calendrier) {
                        $calendrier = new Calendrier;
                        $calendrier->setUser($user)->setAgenda($agenda);
                    }

                    $participer = $calendrier->getParticipe() || $stats['participer'];
                    $interet = $calendrier->getInteret() || $stats['interet'];

                    $calendrier
                        ->setParticipe($participer)
                        ->setInteret($interet);

                    $em->persist($calendrier);
                    $em->flush();
                }
            }
        }
        $maxItems = 50;
        $membres = $this->getFBMembres($agenda, 1, $maxItems);

        return [
            'socials' => $this->getSocialStats($agenda, $request),
            "tendancesParticipations" => $repo->findAllTendancesParticipations($agenda),
            "tendancesInterets" => $repo->findAllTendancesInterets($agenda),
            "count_participer" => $agenda->getParticipations() + $agenda->getFbParticipations(),
            "count_interets" => $agenda->getInterets() + $agenda->getFbInterets(),
            'maxItems' => $maxItems,
            'membres' => $membres,
            'participer' => $participer,
            'interet' => $interet
        ];
    }

    protected function getSocialStats(Agenda $agenda, Request $request)
    {
        $key = 'agenda.stats.' . $agenda->getId();
        $cache = $this->get('memory_cache');
        if (!$cache->contains($key)) {
            $link = $this->generateUrl('tbn_agenda_details', [
                'slug' => $agenda->getSlug(),
            ], true);

            $page = new Page([
                'url' => $link,
                'title' => $agenda->getNom(),
                'text' => $agenda->getDescriptif(),
                'image' => $request->getUriForPath("/" . $agenda->getWebPath()),
            ]);
            $page->shareCount(['twitter', 'facebook', 'plus']);
            $cache->save($key, [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter,
                'google-plus' => $page->plus,
            ], 24 * 3600);
        }

        return $cache->fetch($key);
    }

    protected function handleSearch(SearchAgenda $search, $type, $tag, $ville, Place $place = null)
    {
        if ($ville !== null) {
            $term = null;
            $search->setCommune([$ville]);
            $formAction = $this->generateUrl('tbn_agenda_ville', ['ville' => $ville]);
        } elseif ($place !== null) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('tbn_agenda_place', ['slug' => $place->getSlug()]);
        } elseif ($tag !== null) {
            $term = $tag;
            $formAction = $this->generateUrl('tbn_agenda_tags', ['tag' => $tag]);
        } elseif ($type !== null) {
            $formAction = $this->generateUrl('tbn_agenda_sortir', ['type' => $type]);
            switch ($type) {
                case 'exposition':
                    $term = 'expo, exposition';
                    break;
                case 'concert':
                    $term = 'concert, musique, artiste';
                    break;
                case 'famille':
                    $term = 'famille, enfant';
                    break;
                case 'spectacle':
                    $term = 'spectacle, exposition, théâtre';
                    break;
                case 'etudiant':
                    $term = 'soirée, étudiant, bar, discothèque, boîte de nuit, after';
                    break;
            }
        } else {
            $formAction = $this->generateUrl('tbn_agenda_agenda');
            $term = '';
        }

        $search->setTerm($term);

        return $formAction;
    }

    public function indexAction()
    {
        $siteManager = $this->get('site_manager');
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $site = $siteManager->getCurrentSite();
        $search = (new SearchAgenda)->setDu(null);
        $topEvents = $repo->findTopSoiree($site, 1, 7);

        return $this->render('TBNAgendaBundle:Agenda:index.html.twig', [
            'topEvents' => $topEvents,
            'nbEvents' => $repo->findCountWithSearch($site, $search)
        ]);
    }

    public function listAction(Request $request, $page, $type, $tag, $ville, $slug, $paginateRoute = 'tbn_agenda_pagination')
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();
        $isPost = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = ['page' => $page + 1];
        if($paginateRoute === 'tbn_agenda_sortir_pagination') {
            $routeParams['type'] = $type;
        }elseif($paginateRoute === 'tbn_agenda_tags_pagination') {
            $routeParams['tag'] = $tag;
        }elseif($paginateRoute === 'tbn_agenda_place_pagination') {
            $routeParams['slug'] = $slug;
        }elseif($paginateRoute === 'tbn_agenda_ville_pagination') {
            $routeParams['ville'] = $ville;
        }
        $paginateURL = $this->generateUrl($paginateRoute, $routeParams);

        //Pagination
        $nbSoireeParPage = 15;

        //Gestion du site courant
        $siteManager = $this->container->get('site_manager');
        $site = $siteManager->getCurrentSite();

        //Récupération du repo des événéments
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('TBNAgendaBundle:Agenda');

        //Recherche des événements
        $search = new SearchAgenda();
        $place = null;
        if ($slug !== null) {
            $places = $em->getRepository('TBNAgendaBundle:Place')->findBy(['slug' => $slug]);
            if (isset($places[0])) {
                $place = $places[0];
            }
        }
        $formAction = $this->handleSearch($search, $type, $tag, $ville, $place);

        //Récupération des lieux, types événéments et villes
        $lieux = $this->getPlaces($repo, $site);
        $types_manif = $this->getTypesEvenements($repo, $site);
        $communes = $this->getVilles($repo, $site);

        //Création du formulaire
        $form = $this->createForm(new SearchType($types_manif, $lieux, $communes), $search, [
            'action' => $formAction
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if ($isUserPostSearch) {
            $page = 1;
        } elseif ($isPost) {
            $page = $search->getPage();
        }

        //Recherche ElasticSearch
        $repositoryManager = $this->get('fos_elastica.manager');
        $repository = $repositoryManager->getRepository('TBNAgendaBundle:Agenda');
        $results = $repository->findWithSearch($site, $search); //100ms
        $results->setCurrentPage($page)->setMaxPerPage($nbSoireeParPage);
        $soirees = $results->getCurrentPageResults();
        $nbSoireesTotales = $results->getNbResults();

        return $this->render('TBNAgendaBundle:Agenda:soirees.html.twig', [
            'villeName' => $ville,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'tag' => $tag,
            'type' => $type,
            'soirees' => $soirees,
            'nbEvents' => $nbSoireesTotales,
            'maxPerEvent' => $nbSoireeParPage,
            'page' => $page,
            'search' => $search,
            'isPost' => $isPost,
            'isAjax' => $isAjax,
            'paginateURL' => $paginateURL,
            'form' => $form->createView()
        ]);
    }

    protected function getTypesEvenements($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'categories_evenements.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($site);
            $type_manifestation = [];

            foreach ($soirees_type_manifestation as $soiree) {//
                $types_manifestation = preg_split('/,/', $soiree->getCategorieManifestation());
                foreach ($types_manifestation as $type) {
                    $type = trim($type);
                    if (!in_array($type, $type_manifestation) && $type != '') {
                        $type_manifestation[$type] = $type;
                    }
                }
            }
            ksort($type_manifestation);
            $cache->save($key, $type_manifestation, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getVilles($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'villes.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaVilles($site);
            $tab_villes = [];
            foreach ($places as $place) {
                $tab_villes[$place->getVille()] = $place->getVille();
            }

            $cache->save($key, $tab_villes, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    protected function getPlaces($repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'places.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaPlaces($site);
            $lieux = array();
            foreach ($places as $place) {
                $lieux[$place->getId()] = $place->getNom();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

}
