<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Tag;
use App\Enum\DateRangePreset;
use App\Repository\PlaceRepository;
use App\Repository\TagRepository;
use App\Search\AgendaFacets;
use App\Search\AgendaSection;
use App\Search\DateRange;
use App\Search\SearchEvent;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * The counts of the agenda filters for a search, with the venues and categories they name: Elasticsearch only returns
 * their ids, loaded here in one query each and kept in the order of the counts.
 */
final readonly class AgendaFacetsLoader
{
    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private PlaceRepository $placeRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @param list<AgendaSection> $sections   the days the page lists, which the date filters count too
     * @param int                 $places     how many of the busiest venues to count
     * @param int                 $categories how many of the busiest categories to count under each type
     */
    public function load(SearchEvent $search, array $sections, int $places, int $categories): AgendaFacets
    {
        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);
        $facets = $repository->getFacets($search, $this->getDates($sections), $places, $categories);

        return $facets->withEntities($this->findVenues($facets), $this->findCategoriesByType($facets));
    }

    /**
     * The date windows the filters count: the date shortcuts, and the days the page lists.
     *
     * @param list<AgendaSection> $sections
     *
     * @return array<string, DateRange>
     */
    private function getDates(array $sections): array
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
    private function findVenues(AgendaFacets $facets): array
    {
        if ([] === $facets->places) {
            return [];
        }

        $places = $this->placeRepository->findBy(['id' => array_keys($facets->places)]);
        usort($places, static fn (Place $a, Place $b): int => $facets->places[$b->getId()] <=> $facets->places[$a->getId()]);

        return $places;
    }

    /**
     * The busiest categories of each type, with their counts, in the order of the facets.
     *
     * @return array<string, list<array{tag: Tag, events: int}>> by AgendaType value
     */
    private function findCategoriesByType(AgendaFacets $facets): array
    {
        $ids = array_unique(array_merge(...array_map(array_keys(...), array_values($facets->typeCategories))));
        if ([] === $ids) {
            return [];
        }

        $tags = [];
        foreach ($this->tagRepository->findBy(['id' => $ids]) as $tag) {
            $tags[$tag->getId()] = $tag;
        }

        $categoriesByType = [];
        foreach ($facets->typeCategories as $type => $categories) {
            foreach ($categories as $id => $events) {
                if (isset($tags[$id])) {
                    $categoriesByType[$type][] = ['tag' => $tags[$id], 'events' => $events];
                }
            }
        }

        return $categoriesByType;
    }
}
