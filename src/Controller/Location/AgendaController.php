<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Entity\Event;
use App\Entity\Place;
use App\Form\Type\SearchType;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use App\Utils\TagUtils;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AgendaController extends BaseController
{
    /**
     * @var int
     */
    final public const EVENT_PER_PAGE = 15;

    #[Route(path: '/agenda/{page<%patterns.page%>}', name: 'app_agenda_index', methods: ['GET'])]
    #[Route(path: '/agenda/sortir/{type}/{page<%patterns.page%>}', name: 'app_agenda_by_type', requirements: ['type' => 'concert|spectacle|etudiant|famille|exposition'], methods: ['GET'])]
    #[Route(path: '/agenda/sortir-a/{slug<%patterns.slug%>}/{page<%patterns.page%>}', name: 'app_agenda_by_place', methods: ['GET'])]
    #[Route(path: '/agenda/tag/{tag}/{page<%patterns.page%>}', name: 'app_agenda_by_tags', methods: ['GET'])]
    #[ReverseProxy(expires: 'tomorrow')]
    public function index(Location $location, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, EventRepository $eventRepository, PlaceRepository $placeRepository, int $page = 1, ?string $type = null, ?string $tag = null, ?string $slug = null): Response
    {
        // État de la page
        $isAjax = $request->isXmlHttpRequest();
        $routeParams = array_merge($request->query->all(), [
            'page' => $page + 1,
            'location' => $location->getSlug(),
        ]);
        if (null !== $type) {
            $routeParams['type'] = $type;
        } elseif (null !== $tag) {
            $routeParams['tag'] = $tag;
        } elseif (null !== $slug) {
            $routeParams['slug'] = $slug;
        }

        // Recherche des événements
        $search = new SearchEvent();
        $place = null;
        if (null !== $slug) {
            $place = $placeRepository->findOneBy(['slug' => $slug]);
            if (null === $place) {
                return $this->redirectToRoute('app_agenda_index', ['location' => $location->getSlug()]);
            }

            if ($location->getSlug() !== $place->getLocationSlug()) {
                return $this->redirectToRoute('app_agenda_by_place', ['location' => $place->getLocationSlug(), 'slug' => $place->getSlug()]);
            }
        }

        $formAction = $this->handleSearch($search, $location, $type, $tag, $place);
        // Récupération des lieux, types événements et villes
        $types_manif = $this->getTypesEvenements($memoryCache, $eventRepository, $location);
        // Création du formulaire
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'method' => 'get',
            'types_manif' => $types_manif,
        ]);
        // Bind du formulaire avec la requête courante
        $form->submit($request->query->all(), false);
        if (!$form->isSubmitted() || $form->isValid()) {
            $isValid = true;

            /** @var EventElasticaRepository $repository */
            $repository = $repositoryManager->getRepository(Event::class);
            $events = $repository->findWithSearch($search);
            $this->updatePaginator($events, $page, self::EVENT_PER_PAGE);
        } else {
            $isValid = false;
            $events = $this->createEmptyPaginator($page, self::EVENT_PER_PAGE);
        }

        if ($page > $events->getNbPages()) {
            return $this->redirectToRoute($request->attributes->get('_route'), array_merge($routeParams, ['page' => max(1, $events->getNbPages())]));
        }

        return $this->render('location/agenda/index.html.twig', [
            'location' => $location,
            'placeName' => (null !== $place) ? $place->getName() : null,
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
            'routeParams' => $routeParams,
            'form' => $form,
        ]);
    }

    private function handleSearch(SearchEvent $search, Location $location, ?string $type, ?string $tag, ?Place $place = null): string
    {
        $term = null;
        if (null !== $place) {
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('app_agenda_by_place', ['slug' => $place->getSlug(), 'location' => $location->getSlug()]);
        } elseif (null !== $tag) {
            $search->setTag($tag);
            $formAction = $this->generateUrl('app_agenda_by_tags', ['tag' => $tag, 'location' => $location->getSlug()]);
        } elseif (null !== $type) {
            $formAction = $this->generateUrl('app_agenda_by_type', ['type' => $type, 'location' => $location->getSlug()]);
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
            $formAction = $this->generateUrl('app_agenda_index', ['location' => $location->getSlug()]);
        }

        $search->setLocation($location);
        $search->setTerm($term);

        return $formAction;
    }

    private function getTypesEvenements(CacheInterface $cache, EventRepository $repo, Location $location)
    {
        $key = 'event.categories.v2.' . $location->getSlug();

        return $cache->get($key, static function (ItemInterface $item) use ($repo, $location) {
            $eventTypes = $repo->getEventTypes($location);
            $types = [];
            foreach ($eventTypes as $eventType) {
                $eventTypeTags = TagUtils::getTagTerms($eventType);
                foreach ($eventTypeTags as $eventTypeTag) {
                    $types[$eventTypeTag] = $eventTypeTag;
                }
            }

            ksort($types, \SORT_NATURAL | \SORT_FLAG_CASE);
            $item->expiresAfter(24 * 60 * 60);

            return $types;
        });
    }
}
