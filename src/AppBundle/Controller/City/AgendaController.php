<?php

namespace AppBundle\Controller\City;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\CommentBundle\Entity\Comment;
use TBN\CommentBundle\Form\Type\CommentType;
use TBN\MainBundle\Controller\TBNController as Controller;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use FOS\HttpCacheBundle\Configuration\Tag;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use AppBundle\Configuration\BrowserCache;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Calendrier;

use TBN\AgendaBundle\Form\Type\SearchType;
use TBN\AgendaBundle\Search\SearchAgenda;
use Symfony\Component\HttpFoundation\RedirectResponse;
use TBN\MainBundle\Invalidator\EventInvalidator;
use TBN\UserBundle\Entity\User;

class AgendaController extends Controller
{
    protected function handleSearch(SearchAgenda $search, $type, $tag, $ville, Place $place = null)
    {
        $term = null;
        if ($ville !== null) {
            $term = null;
            $search->setCommune([$ville]);
            $formAction = $this->generateUrl('tbn_agenda_ville', ['ville' => $ville]);
        } elseif ($place !== null) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('tbn_agenda_place', ['slug' => $place->getSlug()]);
        } elseif ($tag !== null) {
            $term = null;
            $search->setTag($tag);
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
        }

        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @Cache(expires="+30 minutes", smaxage="1800")
     * @Route("/agenda", name="tbn_agenda_agenda")
     * @Route("/agenda", name="tbn_agenda_sortir")
     * @Route("/agenda", name="tbn_agenda_place")
     * @Route("/agenda", name="tbn_agenda_tags")
     * @BrowserCache(false)
     */
    public function indexAction(Request $request, $page, $type, $tag, $ville, $slug, $paginateRoute = 'tbn_agenda_pagination')
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();
        $isPost = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = ['page' => $page + 1];
        if ($paginateRoute === 'tbn_agenda_sortir_pagination') {
            $routeParams['type'] = $type;
        } elseif ($paginateRoute === 'tbn_agenda_tags_pagination') {
            $routeParams['tag'] = $tag;
        } elseif ($paginateRoute === 'tbn_agenda_place_pagination') {
            $routeParams['slug'] = $slug;
        } elseif ($paginateRoute === 'tbn_agenda_ville_pagination') {
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
            $place = $em->getRepository('TBNAgendaBundle:Place')->findOneBy(['slug' => $slug]);
            if(! $place) {
                return new RedirectResponse($this->generateUrl('tbn_agenda_agenda'));
            }
        }
        $formAction = $this->handleSearch($search, $type, $tag, $ville, $place);

        //Récupération des lieux, types événéments et villes
        $lieux = $this->getPlaces($repo, $site);
        $types_manif = $this->getTypesEvenements($repo, $site);
        $communes = $this->getVilles($repo, $site);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'lieux' => $lieux,
            'types_manif' => $types_manif,
            'communes' => $communes
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

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($results, $page, $nbSoireeParPage);
        $nbSoireesTotales = $pagination->getTotalItemCount();
        $soirees = $pagination;

        $response = $this->render('TBNAgendaBundle:Agenda:soirees.html.twig', [
            'villeName' => $ville,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'place' => $place,
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

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response;
    }

    protected function getTypesEvenements(AgendaRepository $repo, Site $site)
    {
        $cache = $this->get('memory_cache');
        $key = 'categories_evenements.' . $site->getSubdomain();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($site);
            $type_manifestation = [];

            foreach ($soirees_type_manifestation as $soiree) {//
                $types_manifestation = preg_split('/,/', $soiree->getCategorieManifestation());
                foreach ($types_manifestation as $type) {
                    $type = array_map('trim', explode('//', $type))[0];
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
                $lieux[$place->getNom()] = $place->getId();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

}
