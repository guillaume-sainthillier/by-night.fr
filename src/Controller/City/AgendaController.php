<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\Place;
use App\Form\Type\SearchType;
use App\Repository\EventRepository;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AgendaController extends BaseController
{
    const EVENT_PER_PAGE = 15;

    protected function handleSearch(SearchEvent $search, Location $location, $type, $tag, Place $place = null)
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
                    $term = EventElasticaRepository::EXPO_TERMS;
                    break;
                case 'concert':
                    $term = EventElasticaRepository::CONCERT_TERMS;
                    break;
                case 'famille':
                    $term = EventElasticaRepository::FAMILY_TERMS;
                    break;
                case 'spectacle':
                    $term = EventElasticaRepository::SHOW_TERMS;
                    break;
                case 'etudiant':
                    $term = EventElasticaRepository::STUDENT_TERMS;
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
     * @param int $page
     * @param null $type
     * @param null $tag
     * @param null $slug
     *
     * @return Response
     */
    public function indexAction(Location $location, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, int $page = 1, string $type = null, string $tag = null, string $slug = null)
    {
        //État de la page
        $isAjax = $request->isXmlHttpRequest();

        $routeParams = $request->query->all() + [
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
        $repo = $em->getRepository(Event::class);

        //Recherche des événements
        $search = new SearchEvent();
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
        $types_manif = $this->getTypesEvenements($memoryCache, $repo, $location);

        //Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'method' => 'get',
            'types_manif' => $types_manif,
        ]);

        //Bind du formulaire avec la requête courante
        $form->handleRequest($request);
        if (!$form->isSubmitted() || $form->isValid()) {
            $isValid = true;

            //Recherche ElasticSearch
            $repository = $repositoryManager->getRepository(Event::class);
            $results = $repository->findWithSearch($search);
            $events = new Pagerfanta(new FantaPaginatorAdapter($results));
            $events->setCurrentPage($page)->setMaxPerPage(self::EVENT_PER_PAGE);
        } else {
            $isValid = false;
            $events = new Pagerfanta(new ArrayAdapter([]));
        }

        return $this->render('City/Agenda/index.html.twig', [
            'location' => $location,
            'placeName' => (null !== $place) ? $place->getNom() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'place' => $place,
            'tag' => $tag,
            'type' => $type,
            'events' => $events,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isValid' => $isValid,
            'isAjax' => $isAjax,
            'paginateURL' => $paginateURL,
            'form' => $form->createView(),
        ]);
    }

    private function getTypesEvenements(CacheInterface $cache, EventRepository $repo, Location $location)
    {
        $key = 'categories_evenements.' . $location->getSlug();

        return $cache->get($key, function (ItemInterface $item) use ($repo, $location) {
            $events_type_manifestation = $repo->getTypesEvenements($location);
            $type_manifestation = [];

            foreach ($events_type_manifestation as $event_type_manifestation) {
                $types_manifestation = \explode(',', $event_type_manifestation);
                foreach ($types_manifestation as $type) {
                    $type = \array_map('trim', \explode('//', $type))[0];
                    if (!\in_array($type, $type_manifestation) && '' != $type) {
                        $type_manifestation[$type] = $type;
                    }
                }
            }
            \ksort($type_manifestation);
            $item->expiresAfter(24 * 60 * 60);

            return $type_manifestation;
        });
    }
}
