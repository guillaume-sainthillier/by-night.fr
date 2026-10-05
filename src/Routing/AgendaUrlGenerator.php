<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Routing;

use App\Entity\Place;
use App\Entity\Tag;
use App\Enum\AgendaType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The agenda page of a venue, a category and a type together. Each has an SEO page of its own; combined, the most
 * specific names the path (the venue, else the category, else the type) and the others come in its query string:
 * "/toulouse/agenda/sortir-a/le-bikini?type=student&tag=40". The other filters of the page (dates, keywords, radius)
 * come along.
 */
final readonly class AgendaUrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $filters the other filters of the page, kept in the query string
     */
    #[AsTwigFunction(name: 'agenda_path')]
    public function generate(string $location, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null, array $filters = []): string
    {
        [$route, $parameters] = $this->route($location, $type, $place, $tag);

        return $this->urlGenerator->generate($route, [...$filters, ...$parameters]);
    }

    /**
     * @return array{string, array<string, string|int|null>} the route, and its parameters; the null ones are left out
     *                                                       of the URL
     */
    public function route(string $location, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null): array
    {
        if (null !== $place) {
            return ['app_agenda_by_place', [
                'location' => $place->getLocationSlug(),
                'placeSlug' => $place->getSlug(),
                'type' => $type?->value,
                'tag' => $tag?->getId(),
            ]];
        }

        if (null !== $tag) {
            return ['app_agenda_by_tag', [
                'location' => $location,
                'tagSlug' => $tag->getSlug(),
                'tagId' => $tag->getId(),
                'type' => $type?->value,
            ]];
        }

        if (null !== $type) {
            return ['app_agenda_by_type', ['location' => $location, 'typeSlug' => $type->getSlug()]];
        }

        return ['app_location_index', ['location' => $location]];
    }
}
