<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
use App\Entity\City;
use App\Entity\Place;
use App\Form\Type\SearchType;
use App\Repository\AgendaRepository;
use App\Search\SearchAgenda;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgendaController extends BaseController
{
    const EVENT_PER_PAGE = 15;

    protected function handleSearch(SearchAgenda $search, Location $location, $type, $tag, Place $place = null)
    {
        $term = null;
        if (null !== $place) {
            $term = null;
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('app_agenda_place', ['slug' => $place->getSlug(), 'location' => $location->getSlug()]);
        } elseif (null !== $tag) {
            $term = null;
            $search->setTag($tag);
            $formAction = $this->generateUrl('app_agenda_tags', ['tag' => $tag, 'location' => $location->getSlug()]);
        } elseif (null !== $type) {
            $formAction = $this->generateUrl('app_agenda_sortir', ['type' => $type, 'location' => $location->getSlug()]);
            switch ($type) {
                case 'exposition':
                    $term = \App\SearchRepository\AgendaRepository::EXPO_TERMS;

                    break;
                case 'concert':
                    $term = \App\SearchRepository\AgendaRepository::CONCERT_TERMS;

                    break;
                case 'famille':
                    $term = \App\SearchRepository\AgendaRepository::FAMILY_TERMS;

                    break;
                case 'spectacle':
                    $term = \App\SearchRepository\AgendaRepository::SHOW_TERMS;

                    break;
                case 'etudiant':
                    $term = \App\SearchRepository\AgendaRepository::STUDENT_TERMS;

                    break;
            }
        } else {
            $formAction = $this->generateUrl('app_agenda_agenda', ['location' => $location->getSlug()]);
        }

        $search->setLocation($location);
        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @Route("/agenda/{page}", name="app_agenda_agenda", requirements={"page": "\d+"})
     * @Route("/agenda/sortir/{type}/{page}", name="app_agenda_sortir", requirements={"type": "concert|spectacle|etudiant|famille|exposition", "page": "\d+"})
     * @Route("/agenda/sortir-a/{slug}/{page}", name="app_agenda_place", requirements={"page": "\d+"})
     * @Route("/agenda/tag/{tag}/{page}", name="app_agenda_tags", requirements={"page": "\d+"})
     * @ReverseProxy(expires="+30 minutes")
     *
     * @param Request $request
     * @param City $city
     * @param int $page
     * @param null $type
     * @param null $tag
     * @param null $ville
     * @param null $slug
     * @param RepositoryManagerInterface $repositoryManager
     *
     * @return Response
     */
    public function indexAction(Location $location, Request $request, DoctrineCache $memoryCache, PaginatorInterface $paginator, RepositoryManagerInterface $repositoryManager, $page = 1, $type = null, $tag = null, $slug = null)
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();
        $isPost = $request->isMethod('POST');
        $isUserPostSearch = $isPost && !$isAjax;

        $routeParams = [
            'page' => $page + 1,
            'location' => $location->getSlug(),
        ];

        if (null !== $type) {
            $routeParams['type'] = $type;
        } elseif (null !== $tag) {
            $routeParams['tag'] = $tag;
        } elseif (null !== $slug) {
            $routeParams['slug'] = $slug;
        }

        $paginateURL = $this->generateUrl($request->attributes->get('_route'), $routeParams);

        //Récupération du repo des événements
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        //Recherche des événements
        $search = new SearchAgenda();
        $place = null;

        if (null !== $slug) {
            $place = $em->getRepository(Place::class)->findOneBy(['slug' => $slug]);
            if (!$place) {
                return $this->redirectToRoute('app_agenda_agenda', ['location' => $location->getSlug()]);
            }

            if ($location->getSlug() !== $place->getLocationSlug()) {
                return $this->redirectToRoute('app_agenda_place', ['location' => $place->getLocationSlug(), 'slug' => $place->getSlug()]);
            }
        }
        $formAction = $this->handleSearch($search, $location, $type, $tag, $place);

        //Récupération des lieux, types événements et villes
        $lieux = $this->getPlaces($memoryCache, $repo, $location);
        $types_manif = $this->getTypesEvenements($memoryCache, $repo, $location);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'lieux' => $lieux,
            'types_manif' => $types_manif,
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if ($isUserPostSearch) {
            $page = 1;
        } elseif ($isPost) {
            $page = $search->getPage();
        }

        //Recherche ElasticSearch
        $repository = $repositoryManager->getRepository(Agenda::class);
        $results = $repository->findWithSearch($search);

        $pagination = $paginator->paginate($results, $page, self::EVENT_PER_PAGE);
        $nbSoireesTotales = $pagination->getTotalItemCount();
        $soirees = $pagination;

        return $this->render('City/Agenda/index.html.twig', [
            'location' => $location,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'place' => $place,
            'tag' => $tag,
            'type' => $type,
            'soirees' => $soirees,
            'nbEvents' => $nbSoireesTotales,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isPost' => $isPost,
            'isAjax' => $isAjax,
            'paginateURL' => $paginateURL,
            'form' => $form->createView(),
        ]);
    }

    private function getTypesEvenements(DoctrineCache $cache, AgendaRepository $repo, Location $location)
    {
        $key = 'categories_evenements.' . $location->getSlug();

        if (!$cache->contains($key)) {
            $soirees_type_manifestation = $repo->getTypesEvenements($location);
            $type_manifestation = [];

            foreach ($soirees_type_manifestation as $soiree_type_manifestation) {//
                $types_manifestation = \explode(',', $soiree_type_manifestation);
                foreach ($types_manifestation as $type) {
                    $type = \array_map('trim', \explode('//', $type))[0];
                    if (!\in_array($type, $type_manifestation) && '' != $type) {
                        $type_manifestation[$type] = $type;
                    }
                }
            }
            \ksort($type_manifestation);
            $cache->save($key, $type_manifestation, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }

    private function getPlaces(DoctrineCache $cache, AgendaRepository $repo, Location $location)
    {
        $key = 'places.' . $location->getSlug();

        if (!$cache->contains($key)) {
            $places = $repo->getAgendaPlaces($location);
            $lieux = array();
            foreach ($places as $place) {
                $lieux[$place->getNom()] = $place->getId();
            }

            $cache->save($key, $lieux, 24 * 60 * 60);
        }

        return $cache->fetch($key);
    }
}
