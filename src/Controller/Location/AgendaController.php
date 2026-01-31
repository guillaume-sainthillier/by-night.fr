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
use App\Repository\PlaceRepository;
use App\Repository\TagRepository;
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
    #[Route(path: '/agenda/sortir-a/{slug<%patterns.slug%>}/{page<%patterns.page%>}', name: 'app_agenda_by_place', methods: ['GET'])]
    public function index(AppContext $appContext, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, TagRepository $tagRepository, PlaceRepository $placeRepository, WidgetsManager $widgetsManager, int $page = 1, ?string $type = null, ?string $slug = null): Response
    {
        $location = $appContext->getLocation();

        // Page state
        $isAjax = $request->isXmlHttpRequest();
        $routeParams = array_merge($request->query->all(), [
            'page' => $page + 1,
            'location' => $location->getSlug(),
        ]);
        if (null !== $type) {
            $routeParams['type'] = $type;
        } elseif (null !== $slug) {
            $routeParams['slug'] = $slug;
        }

        // Search for events
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

        $formAction = $this->handleSearch($search, $location, $type, $place);
        // Retrieve tag types for filter
        $types_manif = $this->getTypesEvenements($memoryCache, $tagRepository);
        // Create the form
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

        // Widget data (first page only)
        $topEventsData = $widgetsManager->getTopEventsData($location);
        $topUsersData = $widgetsManager->getTopUsersData();

        return $this->render('location/agenda/index.html.twig', [
            'location' => $location,
            'placeName' => (null !== $place) ? $place->getName() : null,
            'placeSlug' => (null !== $place) ? $place->getSlug() : null,
            'place' => $place,
            'tag' => null,
            'type' => $type,
            'events' => $events,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isValid' => $isValid,
            'isAjax' => $isAjax,
            'routeParams' => $routeParams,
            'form' => $form,
            // Widget data
            'topEventsData' => $topEventsData,
            'topUsersData' => $topUsersData,
        ]);
    }

    /**
     * Canonical tag route with ID in URL.
     */
    #[Route(path: '/agenda/tag/{slug}--{id}/{page<%patterns.page%>}', name: 'app_agenda_by_tag', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function byTag(AppContext $appContext, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, TagRepository $tagRepository, WidgetsManager $widgetsManager, TagRedirectManager $tagRedirectManager, string $slug, int $id, int $page = 1): Response
    {
        $location = $appContext->getLocation();

        // Get tag with SEO redirect if needed
        $tag = $tagRedirectManager->getTag($id, $slug, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);

        return $this->renderTagPage($appContext, $request, $memoryCache, $repositoryManager, $tagRepository, $widgetsManager, $tag, $page);
    }

    /**
     * Legacy tag route (slug only, no ID) - redirects to canonical URL.
     *
     * @deprecated Use app_agenda_by_tag route instead
     */
    #[Route(path: '/agenda/tag/{tag}/{page<%patterns.page%>}', name: 'app_agenda_by_tags', methods: ['GET'])]
    public function byTagsLegacy(AppContext $appContext, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, TagRepository $tagRepository, WidgetsManager $widgetsManager, TagRedirectManager $tagRedirectManager, string $tag, int $page = 1): Response
    {
        $location = $appContext->getLocation();

        // Will throw RedirectException to canonical URL
        $tagEntity = $tagRedirectManager->getTag(null, $tag, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);

        // If no redirect was thrown (e.g., in sub-request), render normally
        return $this->renderTagPage($appContext, $request, $memoryCache, $repositoryManager, $tagRepository, $widgetsManager, $tagEntity, $page);
    }

    private function renderTagPage(AppContext $appContext, Request $request, CacheInterface $memoryCache, RepositoryManagerInterface $repositoryManager, TagRepository $tagRepository, WidgetsManager $widgetsManager, Tag $tag, int $page): Response
    {
        $location = $appContext->getLocation();

        // Page state
        $isAjax = $request->isXmlHttpRequest();
        $routeParams = array_merge($request->query->all(), [
            'page' => $page + 1,
            'location' => $location->getSlug(),
            'slug' => $tag->getSlug(),
            'id' => $tag->getId(),
        ]);

        // Search for events
        $search = new SearchEvent();
        $search->setLocation($location);
        $search->setTagId($tag->getId());

        $formAction = $this->generateUrl('app_agenda_by_tag', ['slug' => $tag->getSlug(), 'id' => $tag->getId(), 'location' => $location->getSlug()]);
        // Retrieve tag types for filter
        $types_manif = $this->getTypesEvenements($memoryCache, $tagRepository);
        // Create the form
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
            return $this->redirectToRoute('app_agenda_by_tag', array_merge($routeParams, ['page' => max(1, $events->getNbPages())]));
        }

        // Widget data (first page only)
        $topEventsData = $widgetsManager->getTopEventsData($location);
        $topUsersData = $widgetsManager->getTopUsersData();

        return $this->render('location/agenda/index.html.twig', [
            'location' => $location,
            'placeName' => null,
            'placeSlug' => null,
            'place' => null,
            'tag' => $tag->getName(),
            'tagEntity' => $tag,
            'type' => null,
            'events' => $events,
            'maxPerEvent' => self::EVENT_PER_PAGE,
            'page' => $page,
            'search' => $search,
            'isValid' => $isValid,
            'isAjax' => $isAjax,
            'routeParams' => $routeParams,
            'form' => $form,
            // Widget data
            'topEventsData' => $topEventsData,
            'topUsersData' => $topUsersData,
        ]);
    }

    private function handleSearch(SearchEvent $search, Location $location, ?string $type, ?Place $place = null): string
    {
        $term = null;
        if (null !== $place) {
            $search->setLieux([$place->getId()]);
            $formAction = $this->generateUrl('app_agenda_by_place', ['slug' => $place->getSlug(), 'location' => $location->getSlug()]);
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

    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    private function getTypesEvenements(CacheInterface $cache, TagRepository $tagRepository): array
    {
        $key = 'event.tags.v3';

        return $cache->get($key, static function (ItemInterface $item) use ($tagRepository) {
            $tags = $tagRepository->findAll();
            $types = [];
            foreach ($tags as $tag) {
                $types[$tag->getName()] = $tag->getName();
            }

            ksort($types, \SORT_NATURAL | \SORT_FLAG_CASE);
            $item->expiresAfter(24 * 60 * 60);

            return $types;
        });
    }
}
