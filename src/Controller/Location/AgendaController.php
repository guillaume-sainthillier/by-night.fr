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
use App\Enum\AgendaType;
use App\Enum\DateRangePreset;
use App\Form\Type\SearchType;
use App\Manager\TagRedirectManager;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Repository\TagRepository;
use App\Routing\AgendaTypeSlugRequirement;
use App\Routing\AgendaUrlGenerator;
use App\Search\AgendaFacets;
use App\Search\AgendaSection;
use App\Search\DateRange;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\ResultWindow;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AgendaController extends BaseController
{
    public const int EVENT_PER_PAGE = 15;

    /** The busiest venues of the filters */
    private const int PLACES = 6;

    /** The categories with the most events to come in the location */
    private const int CATEGORIES = 6;

    /** The busiest categories of the filters under each type */
    private const int TYPE_CATEGORIES = 4;

    #[Route(path: '/agenda/{page<%patterns.page%>}', name: 'app_agenda_index', methods: ['GET'])]
    #[Route(path: '/agenda/sortir/{typeSlug}/{page<%patterns.page%>}', name: 'app_agenda_by_type', requirements: ['typeSlug' => new AgendaTypeSlugRequirement()], methods: ['GET'])]
    #[Route(path: '/agenda/sortir-a/{placeSlug<%patterns.slug%>}/{page<%patterns.page%>}', name: 'app_agenda_by_place', methods: ['GET'])]
    #[Route(path: '/agenda/tag/{tagSlug<%patterns.slug%>}--{tagId<%patterns.id%>}/{page<%patterns.page%>}', name: 'app_agenda_by_tag', requirements: ['tagId' => '\d+'], methods: ['GET'])]
    #[Route(path: '/agenda/tag/{legacyTag}/{page<%patterns.page%>}', name: 'app_agenda_by_tags', methods: ['GET'])]
    public function index(
        AppContext $appContext,
        Request $request,
        RepositoryManagerInterface $repositoryManager,
        EventRepository $eventRepository,
        PlaceRepository $placeRepository,
        TagRepository $tagRepository,
        TagRedirectManager $tagRedirectManager,
        AgendaUrlGenerator $agendaUrlGenerator,
        int $page = 1,
        ?string $typeSlug = null,
        ?string $placeSlug = null,
        ?string $tagSlug = null,
        ?int $tagId = null,
        ?string $legacyTag = null,
    ): Response {
        $location = $appContext->getLocation();
        $type = null !== $typeSlug ? AgendaType::fromSlug($typeSlug) : null;
        $place = null;
        $tag = null;

        if (null === $placeSlug && 'app_agenda_by_place' === $request->attributes->getString('_route')) {
            return $this->redirectLegacyPlaceUrl($request, $location, $placeRepository);
        }

        // Handle place filtering
        if (null !== $placeSlug) {
            $place = $this->findPlace($placeRepository, $location, $placeSlug);
            if (null === $place) {
                return $this->redirectToRoute('app_agenda_index', ['location' => $location->getSlug()]);
            }

            // Another location, or the slug of a place merged into this one
            if ($location->getSlug() !== $place->getLocationSlug() || $placeSlug !== $place->getSlug()) {
                return $this->redirectToRoute('app_agenda_by_place', [...$request->query->all(), 'location' => $place->getLocationSlug(), 'placeSlug' => $place->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
            }

            // Its path names the venue: a type and a category narrowing it down come as "?type=student&tag=40"
            $type = AgendaType::tryFrom($request->query->getString('type'));
            $categoryId = $request->query->getInt('tag');
            $tag = $categoryId > 0 ? $tagRepository->find($categoryId) : null;
        }

        // Handle tag filtering (canonical route with ID)
        if (null !== $tagId) {
            $tag = $tagRedirectManager->getTag($tagId, $tagSlug, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // Handle legacy tag route (slug only, no ID) - redirects to canonical URL
        if (null !== $legacyTag) {
            $tag = $tagRedirectManager->getTag(null, $legacyTag, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // Like a venue, a category narrowed down to a type takes it as "?type=student"
        if (null === $place && null !== $tag) {
            $type = AgendaType::tryFrom($request->query->getString('type'));
        }

        // Search for events
        $search = new SearchEvent();
        $formAction = $this->handleSearch($search, $location, $type, $place, $tag);

        // Create and submit the form
        $form = $this->createForm(SearchType::class, $search, [
            'action' => $formAction,
            'method' => 'get',
        ]);
        $form->submit($request->query->all(), false);

        // The parameters of the page's route but its page number, the type and the category of a venue included
        [, $routeParams] = $agendaUrlGenerator->route($location->getSlug(), $type, $place, $tag);
        $filters = $this->buildFilters($request, $search);

        // Execute search
        /** @var EventElasticaRepository $repository */
        $repository = $repositoryManager->getRepository(Event::class);
        $isValid = !$form->isSubmitted() || $form->isValid();
        if ($isValid) {
            $eventsAdapter = $repository->findWithSearch($search);
            $events = $this->createMultipleEagerLoadingPaginatorFromAdapter(
                $eventsAdapter,
                $eventRepository,
                $page,
                self::EVENT_PER_PAGE,
                ['view' => 'events:agenda:list'],
            );
            // Pages past the result window redirect to the last one below
            $events->setMaxNbPages(ResultWindow::getMaxPages(self::EVENT_PER_PAGE));
        } else {
            $events = $this->createEmptyPaginator($page, self::EVENT_PER_PAGE);
        }

        // Redirect if page exceeds results
        if ($page > $events->getNbPages()) {
            return $this->redirectToRoute($request->attributes->getString('_route'), [...$routeParams, ...$filters, 'page' => max(1, $events->getNbPages())]);
        }

        $dateRange = $search->getDateRange();
        $sections = AgendaSection::fromEvents($events->getCurrentPageResults(), $dateRange->from, $dateRange->to);

        $facets = new AgendaFacets();
        if ($isValid) {
            $facets = $repository->getFacets($search, $this->getFacetDates($sections), self::PLACES, self::TYPE_CATEGORIES);
        }

        return $this->render('location/agenda/index.html.twig', [
            'location' => $location,
            'placeName' => $place?->getName(),
            'placeSlug' => $place?->getSlug(),
            'place' => $place,
            'tag' => $tag?->getName(),
            'tagEntity' => $tag,
            'type' => $type,
            'events' => $events,
            'sections' => $sections,
            'facets' => $facets,
            'places' => $this->getPlaces($placeRepository, $facets),
            'categories' => $eventRepository->findUpcomingCategories($location, self::CATEGORIES),
            'typeCategories' => $this->getTypeCategories($tagRepository, $facets),
            'page' => $page,
            'search' => $search,
            'dateRange' => $dateRange,
            'isValid' => $isValid,
            'routeParams' => $routeParams,
            'filters' => $filters,
            'undatedFilters' => array_diff_key($filters, ['when' => true, 'dateRange' => true]),
            'form' => $form,
        ]);
    }

    /**
     * "/agenda/sortir-a" without a place is not a page of its own. The sitemap used to link it
     * with the place as "?slug=…", so that parameter still leads to the place's own URL, in the
     * place's own city; anything else goes to the city agenda.
     */
    private function redirectLegacyPlaceUrl(Request $request, Location $location, PlaceRepository $placeRepository): Response
    {
        $legacySlug = $request->query->getString('slug');
        $place = null;
        if ('' !== $legacySlug) {
            $place = $this->findPlace($placeRepository, $location, $legacySlug);
        }

        if (null === $place) {
            return $this->redirectToRoute('app_agenda_index', ['location' => $location->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->redirectToRoute('app_agenda_by_place', [
            'location' => $place->getLocationSlug(),
            'placeSlug' => $place->getSlug(),
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Place slugs are not unique ("salle-des-fetes" names hundreds of places): the place in the city
     * the URL names (or without a city, in its country) comes first; another one is only a fallback,
     * which the caller redirects to its own URL. The location's city and country are lazy proxies,
     * hence their ids rather than the objects.
     */
    private function findPlace(PlaceRepository $placeRepository, Location $location, string $slug): ?Place
    {
        $city = $location->getCity();
        $country = $location->getCountry();
        $place = match (true) {
            null !== $city => $placeRepository->findOneBy(['slug' => $slug, 'city' => $city->getId()]),
            null !== $country => $placeRepository->findOneBy(['slug' => $slug, 'country' => $country->getId(), 'city' => null]),
            default => null,
        };

        return $place
            // The slug of a place merged into another one of its city (app:places:merge-duplicates)
            ?? $placeRepository->findOneByLegacySlug($slug, $location)
            ?? $placeRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * The filters of the query string, which the links of the page keep: the period, the keywords, the radius. The type
     * and the category are left out, as the route parameters already hold them (AgendaUrlGenerator::route()). The period is the search's: its
     * shortcut by name, which stays true the next week, else the dates picked.
     *
     * @return array<string, mixed>
     */
    private function buildFilters(Request $request, SearchEvent $search): array
    {
        $filters = $request->query->all();
        unset($filters['page'], $filters['type'], $filters['tag'], $filters['when'], $filters['dateRange']);
        if (null === $search->getTerm()) {
            unset($filters['term']);
        }

        $preset = $search->getPreset();
        if (null === $preset) {
            $filters['dateRange'] = $search->getDateRange()->toQuery();
        } elseif (DateRangePreset::Anytime !== $preset) {
            $filters['when'] = $preset->value;
        }

        return $filters;
    }

    /**
     * The date windows the filters count: the date shortcuts, and the days the page lists.
     *
     * @param list<AgendaSection> $sections
     *
     * @return array<string, DateRange>
     */
    private function getFacetDates(array $sections): array
    {
        $dates = [];
        foreach (DateRangePreset::cases() as $preset) {
            $dates[$preset->value] = $preset->range();
        }

        foreach ($sections as $section) {
            $dates[$section->day->format('Y-m-d')] = new DateRange($section->day, $section->day);
        }

        return $dates;
    }

    /**
     * @return list<Place> the busiest venues, the busiest first
     */
    private function getPlaces(PlaceRepository $placeRepository, AgendaFacets $facets): array
    {
        if ([] === $facets->places) {
            return [];
        }

        $places = $placeRepository->findBy(['id' => array_keys($facets->places)]);
        usort($places, static fn (Place $a, Place $b): int => $facets->places[$b->getId()] <=> $facets->places[$a->getId()]);

        return $places;
    }

    /**
     * The busiest categories of each type, with their counts, in the order of the facets.
     *
     * @return array<string, list<array{tag: Tag, events: int}>> by AgendaType value
     */
    private function getTypeCategories(TagRepository $tagRepository, AgendaFacets $facets): array
    {
        $ids = array_unique(array_merge(...array_map(array_keys(...), array_values($facets->typeCategories))));
        if ([] === $ids) {
            return [];
        }

        $tags = [];
        foreach ($tagRepository->findBy(['id' => $ids]) as $tag) {
            $tags[$tag->getId()] = $tag;
        }

        $typeCategories = [];
        foreach ($facets->typeCategories as $type => $categories) {
            foreach ($categories as $id => $events) {
                if (isset($tags[$id])) {
                    $typeCategories[$type][] = ['tag' => $tags[$id], 'events' => $events];
                }
            }
        }

        return $typeCategories;
    }

    private function handleSearch(SearchEvent $search, Location $location, ?AgendaType $type, ?Place $place, ?Tag $tag): string
    {
        if (null !== $place) {
            $search->setLieux([$place->getId()]);
        }

        if (null !== $tag) {
            $search->setTagId($tag->getId());
        }

        $search->setLocation($location);
        $search->setType($type);

        // The path of the page: a GET form drops the query string of its action, so the type and the category of a
        // venue come as hidden fields (location/agenda/_filters.html.twig)
        return $this->generateUrl(...match (true) {
            null !== $place => ['app_agenda_by_place', ['placeSlug' => $place->getSlug(), 'location' => $location->getSlug()]],
            null !== $tag => ['app_agenda_by_tag', ['tagSlug' => $tag->getSlug(), 'tagId' => $tag->getId(), 'location' => $location->getSlug()]],
            null !== $type => ['app_agenda_by_type', ['typeSlug' => $type->getSlug(), 'location' => $location->getSlug()]],
            default => ['app_agenda_index', ['location' => $location->getSlug()]],
        });
    }
}
