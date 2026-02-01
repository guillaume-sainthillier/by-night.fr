<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\AppContext;
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Form\Type\SearchType;
use App\Manager\TagRedirectManager;
use App\Manager\WidgetsManager;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class AgendaController extends BaseController
{
    public const int EVENT_PER_PAGE = 15;

    #[Route(path: '/agenda/{page<%patterns.page%>}', name: 'app_agenda_index', methods: ['GET'])]
    #[Route(path: '/agenda/sortir/{type}/{page<%patterns.page%>}', name: 'app_agenda_by_type', requirements: ['type' => 'concert|spectacle|etudiant|famille|exposition'], methods: ['GET'])]
    #[Route(path: '/agenda/sortir-a/{placeSlug<%patterns.slug%>}/{page<%patterns.page%>}', name: 'app_agenda_by_place', methods: ['GET'])]
    #[Route(path: '/agenda/tag/{tagSlug}--{tagId}/{page<%patterns.page%>}', name: 'app_agenda_by_tag', requirements: ['tagId' => '\d+'], methods: ['GET'])]
    #[Route(path: '/agenda/tag/{legacyTag}/{page<%patterns.page%>}', name: 'app_agenda_by_tags', methods: ['GET'])]
    public function index(
        AppContext $appContext,
        Request $request,
        CacheInterface $memoryCache,
        RepositoryManagerInterface $repositoryManager,
        EventRepository $eventRepository,
        PlaceRepository $placeRepository,
        WidgetsManager $widgetsManager,
        TagRedirectManager $tagRedirectManager,
        int $page = 1,
        ?string $type = null,
        ?string $placeSlug = null,
        ?string $tagSlug = null,
        ?int $tagId = null,
        ?string $legacyTag = null,
    ): Response {
        $location = $appContext->getLocation();
        $place = null;
        $tag = null;

        // Handle place filtering
        if (null !== $placeSlug) {
            $place = $placeRepository->findOneBy(['slug' => $placeSlug]);
            if (null === $place) {
                return $this->redirectToRoute('app_agenda_index', ['location' => $location->getSlug()]);
            }

            if ($location->getSlug() !== $place->getLocationSlug()) {
                return $this->redirectToRoute('app_agenda_by_place', ['location' => $place->getLocationSlug(), 'placeSlug' => $place->getSlug()]);
            }
        }

        // Handle tag filtering (canonical route with ID)
        if (null !== $tagId) {
            $tag = $tagRedirectManager->getTag($tagId, $tagSlug, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // Handle legacy tag route (slug only, no ID) - redirects to canonical URL
        if (null !== $legacyTag) {
            $tag = $tagRedirectManager->getTag(null, $legacyTag, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // Build route params for pagination
        $routeParams = $this->buildRouteParams($request, $location, $page, $type, $place, $tag);

        // Search for events
        $search = new SearchEvent();
        $formAction = $this->handleSearch($search, $location, $type, $place, $tag);

        // Retrieve tag types for filter
        $types_manif = $this->getTypesEvenements($memoryCache, $eventRepository, $location);

        // Create and submit the form
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'method' => 'get',
            'types_manif' => $types_manif,
        ]);
        $form->submit($request->query->all(), false);

        // Execute search
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

        // Redirect if page exceeds results
        if ($page > $events->getNbPages()) {
            return $this->redirectToRoute($request->attributes->get('_route'), array_merge($routeParams, ['page' => max(1, $events->getNbPages())]));
        }

        // Widget data
        $topEventsData = $widgetsManager->getTopEventsData($location);
        $topUsersData = $widgetsManager->getTopUsersData();

        return $this->render('location/agenda/index.html.twig', [
            'location' => $location,
            'placeName' => $place?->getName(),
            'placeSlug' => $place?->getSlug(),
            'place' => $place,
            'tag' => $tag?->getName(),
            'tagEntity' => $tag,
            'type' => $type,
            'events' => $events,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isValid' => $isValid,
            'isAjax' => $request->isXmlHttpRequest(),
            'routeParams' => $routeParams,
            'form' => $form,
            'topEventsData' => $topEventsData,
            'topUsersData' => $topUsersData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRouteParams(Request $request, Location $location, int $page, ?string $type, ?Place $place, ?Tag $tag): array
    {
        $routeParams = array_merge($request->query->all(), [
            'page' => $page + 1,
            'location' => $location->getSlug(),
        ]);

        if (null !== $tag) {
            $routeParams['tagSlug'] = $tag->getSlug();
            $routeParams['tagId'] = $tag->getId();
        } elseif (null !== $type) {
            $routeParams['type'] = $type;
        } elseif (null !== $place) {
            $routeParams['placeSlug'] = $place->getSlug();
        }

        return $routeParams;
    }

    private function handleSearch(SearchEvent $search, Location $location, ?string $type, ?Place $place, ?Tag $tag): string
    {
        $term = null;

        if (null !== $tag) {
            $search->setTagId($tag->getId());
            $formAction = $this->generateUrl('app_agenda_by_tag', [
                'tagSlug' => $tag->getSlug(),
                'tagId' => $tag->getId(),
                'location' => $location->getSlug(),
            ]);
        } elseif (null !== $place) {
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('app_agenda_by_place', ['placeSlug' => $place->getSlug(), 'location' => $location->getSlug()]);
        } elseif (null !== $type) {
            $formAction = $this->generateUrl('app_agenda_by_type', ['type' => $type, 'location' => $location->getSlug()]);
            $term = match ($type) {
                'exposition' => EventElasticaRepository::EXPO_TERMS,
                'concert' => EventElasticaRepository::CONCERT_TERMS,
                'famille' => EventElasticaRepository::FAMILY_TERMS,
                'spectacle' => EventElasticaRepository::SHOW_TERMS,
                'etudiant' => EventElasticaRepository::STUDENT_TERMS,
                default => null,
            };
        } else {
            $formAction = $this->generateUrl('app_agenda_index', ['location' => $location->getSlug()]);
        }

        $search->setLocation($location);
        $search->setTerm($term);

        return $formAction;
    }

    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    private function getTypesEvenements(CacheInterface $cache, EventRepository $repo, Location $location): array
    {
        $key = 'event.categories.v3.' . $location->getSlug();

        return $cache->get($key, static function (ItemInterface $item) use ($repo, $location) {
            $eventCategories = $repo->getEventTypes($location);
            $types = [];
            foreach ($eventCategories as $eventCategory) {
                $types[$eventCategory->getName()] = $eventCategory->getName();
            }

            ksort($types, \SORT_NATURAL | \SORT_FLAG_CASE);
            $item->expiresAfter(24 * 60 * 60);

            return $types;
        });
    }
}
