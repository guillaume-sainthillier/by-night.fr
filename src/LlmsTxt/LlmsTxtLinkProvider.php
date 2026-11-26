<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\LlmsTxt;

use App\Controller\Location\AgendaController;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use Generator;
use Silarhi\LlmsTxtBundle\Attribute\AsLlmsTxtLinkProvider;
use Silarhi\LlmsTxtBundle\Model\Link;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The sections of /llms.txt, in their order in the file: an entry point to the agenda, not a copy of the sitemap. The
 * countries and the cities with events to come (their location page is noindex without any), the editorial pages
 * (their Markdown version, and their content in llms-full.txt), the MCP server; the static pages come from the "llms_txt" option of their route. Left out: the events (millions, most of them short-lived), the venues and the
 * per-type or per-category agendas (filters of a city agenda, which links to them), and the member profiles.
 *
 * Every query reads the counts UpcomingEventCounter stores, never the events themselves: light enough to build the
 * file on the fly.
 */
final readonly class LlmsTxtLinkProvider
{
    /**
     * A city needs a full agenda page of events to come to be listed, the threshold SitemapListener sets for its
     * per-category pages: a few hundred busy cities instead of thousands of villages with an event or two.
     */
    public const int CITY_MIN_EVENTS = AgendaController::EVENT_PER_PAGE;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EventRepository $eventRepository,
        private CityRepository $cityRepository,
        private PageRepository $pageRepository,
        private Environment $twig,
    ) {
    }

    /**
     * @return Generator<int, Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Agendas par pays')]
    public function getCountryLinks(): Generator
    {
        foreach ($this->eventRepository->getCountryEvents() as $country) {
            yield new Link(
                $this->url('app_location_index', ['location' => $country['slug']]),
                $country['displayName'],
                $this->upcoming((int) $country['events']),
            );
        }
    }

    /**
     * @return Generator<int, Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Agendas par ville')]
    public function getCityLinks(): Generator
    {
        foreach ($this->cityRepository->findAllLlmsTxt(self::CITY_MIN_EVENTS) as $city) {
            // The department tells the many homonyms apart (Saint-Denis, Valence...)
            $zone = implode(', ', array_filter([$city['department'], $city['country']]));

            yield new Link(
                $this->url('app_location_index', ['location' => $city['slug']]),
                $city['name'],
                \sprintf('%s : %s', $zone, $this->upcoming((int) $city['events'])),
            );
        }
    }

    /**
     * The Markdown version of each page, also its content in llms-full.txt: loaded only when that file is rendered.
     *
     * @return Generator<int, Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Articles')]
    public function getPageLinks(): Generator
    {
        foreach ($this->pageRepository->findAllLlmsTxt() as $page) {
            $id = $page['id'];

            yield new Link(
                $this->url('app_page_show', ['slug' => $page['slug'], '_format' => 'md']),
                $page['title'],
                $page['metaDescription'],
                content: fn (): string => $this->renderPage($id),
            );
        }
    }

    /**
     * @return list<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Pour les assistants')]
    public function getAssistantLinks(): array
    {
        return [new Link(
            $this->url('_mcp_endpoint_default'),
            'Serveur MCP',
            "L'agenda en Model Context Protocol (Streamable HTTP) : find_cities, search_events, get_event",
        )];
    }

    /**
     * The page as /p/{slug}.md shows it, without the title llms-full.txt writes above it; nothing for a page deleted
     * since it was listed, which the file leaves out.
     */
    private function renderPage(int $id): string
    {
        $page = $this->pageRepository->find($id);
        if (null === $page) {
            return '';
        }

        return $this->twig->render('page/_content.md.twig', ['page' => $page]);
    }

    /**
     * Worded as the location pages word it: "1 sortie à venir", "2851 sorties à venir".
     */
    private function upcoming(int $events): string
    {
        return \sprintf('%d %s à venir', $events, 1 === $events ? 'sortie' : 'sorties');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters = []): string
    {
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
