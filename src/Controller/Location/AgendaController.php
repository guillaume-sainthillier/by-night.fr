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
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Enum\AgendaType;
use App\Enum\DateRangePreset;
use App\Form\Type\SearchType;
use App\Manager\PlaceRedirectManager;
use App\Manager\TagRedirectManager;
use App\Repository\EventRepository;
use App\Routing\AgendaTypeSlugRequirement;
use App\Routing\AgendaUrlGenerator;
use App\Search\AgendaFacets;
use App\Search\AgendaSection;
use App\Search\SearchEvent;
use App\SearchRepository\AgendaFacetsLoader;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\ResultWindow;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
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

    /** Also the busiest cities on each country card, here and on the home page: they share the query */
    public const int VENUES = 5;

    /** Below, a country has no ranking of its cities */
    private const int MIN_CITIES = 3;

    private const int NEIGHBOURS = 5;

    // Shared by the CDN for visitors only (SharedCacheSubscriber); never by a browser, which would keep it after a login
    #[Cache(maxage: 0, smaxage: 600, public: true, staleWhileRevalidate: 600, staleIfError: 86400)]
    #[Route(path: '/{page<%patterns.page%>}', name: 'app_location_index', methods: ['GET'])]
    #[Route(path: '/agenda/sortir/{typeSlug}/{page<%patterns.page%>}', name: 'app_agenda_by_type', requirements: ['typeSlug' => new AgendaTypeSlugRequirement()], methods: ['GET'])]
    #[Route(path: '/agenda/sortir-a/{placeSlug<%patterns.slug%>}/{page<%patterns.page%>}', name: 'app_agenda_by_place', methods: ['GET'])]
    #[Route(path: '/agenda/tag/{tagSlug<%patterns.slug%>}--{tagId<%patterns.id%>}/{page<%patterns.page%>}', name: 'app_agenda_by_tag', requirements: ['tagId' => '\d+'], methods: ['GET'])]
    #[Route(path: '/agenda/tag/{legacyTag}/{page<%patterns.page%>}', name: 'app_agenda_by_tags', methods: ['GET'])]
    public function index(
        AppContext $appContext,
        Request $request,
        RepositoryManagerInterface $repositoryManager,
        EventRepository $eventRepository,
        PlaceRedirectManager $placeRedirectManager,
        TagRedirectManager $tagRedirectManager,
        AgendaUrlGenerator $agendaUrlGenerator,
        AgendaFacetsLoader $agendaFacetsLoader,
        int $page = 1,
        ?string $typeSlug = null,
        ?string $placeSlug = null,
        ?string $tagSlug = null,
        ?int $tagId = null,
        ?string $legacyTag = null,
    ): Response {
        $location = $appContext->getLocation();
        // The page of the location itself: "/toulouse", "/toulouse/2"
        $isLocationPage = 'app_location_index' === $request->attributes->getString('_route');

        $type = null !== $typeSlug ? AgendaType::fromSlug($typeSlug) : null;
        $place = null;
        $tag = null;

        // The venue of the URL, else a redirect to its own URL or to the location's page
        if ('app_agenda_by_place' === $request->attributes->getString('_route')) {
            $place = $placeRedirectManager->getPlace($placeSlug, $location);
        }

        // Handle tag filtering (canonical route with ID)
        if (null !== $tagId) {
            $tag = $tagRedirectManager->getTag($tagId, $tagSlug, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // Handle legacy tag route (slug only, no ID) - redirects to canonical URL
        if (null !== $legacyTag) {
            $tag = $tagRedirectManager->getTag(null, $legacyTag, $location->getSlug(), 'app_agenda_by_tag', ['page' => $page]);
        }

        // A venue or a category page narrowed down by the query string: "?type=student&tag=40"
        [$type, $tag] = $agendaUrlGenerator->resolveQuery($request, $type, $place, $tag);

        // Search for events
        $search = $this->createSearch($location, $type, $place, $tag);
        [$formAction, $formQuery] = $agendaUrlGenerator->formTarget($location->getSlug(), $type, $place, $tag);

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

        // Redirect if page exceeds results; the first page goes without its number
        if ($page > $events->getNbPages()) {
            $lastPage = $events->getNbPages();

            return $this->redirectToRoute($request->attributes->getString('_route'), [...$routeParams, ...$filters, 'page' => $lastPage > 1 ? $lastPage : null]);
        }

        $dateRange = $search->getDateRange();
        $sections = AgendaSection::fromEvents($events->getCurrentPageResults(), $dateRange->from, $dateRange->to);

        $facets = $isValid ? $agendaFacetsLoader->load($search, $sections, self::PLACES, self::TYPE_CATEGORIES) : new AgendaFacets();

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
            'categories' => $eventRepository->findUpcomingCategories($location, self::CATEGORIES),
            'page' => $page,
            'search' => $search,
            'dateRange' => $dateRange,
            'isValid' => $isValid,
            'routeParams' => $routeParams,
            'formQuery' => $formQuery,
            'filters' => $filters,
            'undatedFilters' => array_diff_key($filters, ['when' => true, 'dateRange' => true]),
            'unpricedFilters' => array_diff_key($filters, ['price' => true]),
            'form' => $form,
            // The first page of the location also introduces it: its busiest cities, and its neighbours
            'portal' => $isLocationPage && 1 === $page ? $this->getPortal($eventRepository, $location) : null,
        ]);
    }

    /**
     * The location's agenda used to be a page of its own, besides the page of the location: they are one page now.
     */
    #[Route(path: '/agenda/{page<%patterns.page%>}', name: 'app_agenda_legacy', methods: ['GET'])]
    public function legacyIndex(Request $request, string $location, int $page = 1): Response
    {
        return $this->redirectToRoute('app_location_index', [
            ...$request->query->all(),
            'location' => $location,
            'page' => $page > 1 ? $page : null,
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return array{
     *     cities: list<array{0: City, events: int|string}>,
     *     neighbours: list<array{0: City|Country, events: int|string}>,
     * }
     */
    private function getPortal(EventRepository $eventRepository, Location $location): array
    {
        $cities = [];
        if ($location->isCity()) {
            // The cities around the city
            $neighbours = $eventRepository->findUpcomingCitiesAround($location->getCity(), self::NEIGHBOURS);
        } else {
            // The busiest cities of the country, and the other countries, as the home page lists them
            $cities = $eventRepository->findUpcomingCitiesOfCountry($location->getCountry(), self::VENUES);
            $neighbours = $eventRepository->findUpcomingCountries(self::VENUES, $location->getCountry());
        }

        return [
            // A country with too few busy cities to rank (Monaco) has its venues in the filters of its agenda
            'cities' => \count($cities) >= self::MIN_CITIES ? $cities : [],
            'neighbours' => $neighbours,
        ];
    }

    /**
     * The filters of the query string, which the links of the page keep: the period, the price, the keywords, the radius. The type
     * and the category are left out, as the route parameters already hold them (AgendaUrlGenerator::route()). The period is the search's: its
     * shortcut by name, which stays true the next week, else the dates picked. An unknown price shortcut is left out.
     *
     * @return array<string, mixed>
     */
    private function buildFilters(Request $request, SearchEvent $search): array
    {
        $filters = $request->query->all();
        unset($filters['page'], $filters[AgendaUrlGenerator::TYPE], $filters[AgendaUrlGenerator::CATEGORY], $filters['when'], $filters['dateRange'], $filters['price']);
        if (null === $search->getTerm()) {
            unset($filters['term']);
        }

        $preset = $search->getPreset();
        if (null === $preset) {
            $filters['dateRange'] = $search->getDateRange()->toQuery();
        } elseif (DateRangePreset::Anytime !== $preset) {
            $filters['when'] = $preset->value;
        }

        if (null !== $search->getPrice()) {
            $filters['price'] = $search->getPrice()->value;
        }

        return $filters;
    }

    private function createSearch(Location $location, ?AgendaType $type, ?Place $place, ?Tag $tag): SearchEvent
    {
        $search = new SearchEvent();
        if (null !== $place) {
            $search->setLieux([$place->getId()]);
        }

        if (null !== $tag) {
            $search->setTagId($tag->getId());
        }

        $search->setLocation($location);
        $search->setType($type);

        return $search;
    }
}
