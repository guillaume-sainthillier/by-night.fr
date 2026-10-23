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
use App\Enum\DateRangePreset;
use App\Repository\TagRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The agenda page of a venue, a category and a type together. Each has an SEO page of its own; combined, the most
 * specific names the path (the venue, else the category, else the type) and the others come in its query string:
 * "/toulouse/agenda/sortir-a/le-bikini?type=student&tag=40". The other filters of the page (dates, keywords, radius)
 * come along.
 *
 * The date shortcuts searched for by name have a page of their own under the location ("/toulouse/ce-week-end", see
 * DateRangePreset::getPageSlug()): the location's agenda with such a shortcut is that page, its other filters in its
 * query string. Narrowed down by a type, a venue or a category, the shortcut stays in the query string ("?when=…").
 *
 * The URLs are built (generate(), route()) and read back (resolveQuery()) here only, so that both sides keep the same
 * rule.
 */
final readonly class AgendaUrlGenerator
{
    /** The query parameter of the type, on a venue or a category page */
    public const string TYPE = 'type';

    /** The query parameter of the category, on a venue page */
    public const string CATEGORY = 'tag';

    /** The query parameter of the date shortcut, unless the path names it */
    public const string WHEN = 'when';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters the other filters of the page, kept in the query string; their date shortcut
     *                                      ("when") moves to the path when it has a page, and a "page" number past
     *                                      the first one goes to the path
     */
    #[AsTwigFunction(name: 'agenda_path')]
    public function generate(string $location, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null, array $filters = []): string
    {
        $when = DateRangePreset::tryFrom(\is_string($filters[self::WHEN] ?? null) ? $filters[self::WHEN] : '');
        [$route, $parameters] = $this->route($location, $type, $place, $tag, $when);

        return $this->urlGenerator->generate($route, [...$filters, ...$parameters]);
    }

    /**
     * @return array{string, array<string, string|int|null>} the route, and its parameters; the null ones are left out
     *                                                       of the URL
     */
    public function route(string $location, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null, ?DateRangePreset $when = null): array
    {
        if (null !== $place) {
            return ['app_agenda_by_place', [
                'location' => $place->getLocationSlug(),
                'placeSlug' => $place->getSlug(),
                self::TYPE => $type?->value,
                self::CATEGORY => $tag?->getId(),
            ]];
        }

        if (null !== $tag) {
            return ['app_agenda_by_tag', [
                'location' => $location,
                'tagSlug' => $tag->getSlug(),
                'tagId' => $tag->getId(),
                self::TYPE => $type?->value,
            ]];
        }

        if (null !== $type) {
            return ['app_agenda_by_type', ['location' => $location, 'typeSlug' => $type->getSlug()]];
        }

        $dateSlug = $when?->getPageSlug();
        if (null !== $dateSlug) {
            // The path names the shortcut: none in the query string
            return ['app_agenda_by_date', ['location' => $location, 'dateSlug' => $dateSlug, self::WHEN => null]];
        }

        return ['app_location_index', ['location' => $location]];
    }

    /**
     * The action of the page's GET form, and the fields it has to carry: such a form drops the query string of its
     * action, so the type and the category a venue or a category page keep there come as hidden fields.
     *
     * @return array{string, array<string, string|int>} the path, and the hidden fields by name
     */
    public function formTarget(string $location, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null, ?DateRangePreset $when = null): array
    {
        [$route, $parameters] = $this->route($location, $type, $place, $tag, $when);
        $query = array_filter(
            array_intersect_key($parameters, [self::TYPE => true, self::CATEGORY => true]),
            static fn (string|int|null $value): bool => null !== $value,
        );

        return [$this->urlGenerator->generate($route, array_diff_key($parameters, $query)), $query];
    }

    /**
     * The type and the category of an agenda page, given the filters its path names: a venue page takes its type and
     * its category from the query string, a category page its type (see route()). An unknown one is left out.
     *
     * @return array{AgendaType|null, Tag|null}
     */
    public function resolveQuery(Request $request, ?AgendaType $type = null, ?Place $place = null, ?Tag $tag = null): array
    {
        if (null === $place && null === $tag) {
            return [$type, null];
        }

        $type = AgendaType::tryFrom($request->query->getString(self::TYPE));
        if (null === $place) {
            return [$type, $tag];
        }

        $tagId = $request->query->getInt(self::CATEGORY);

        return [$type, $tagId > 0 ? $this->tagRepository->find($tagId) : null];
    }
}
