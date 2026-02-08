<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;

final readonly class TagConverter
{
    public function __construct(private TagRepository $tagRepository)
    {
    }

    /**
     * Parse a tag string into individual terms.
     *
     * Splits on comma and slash separators, trims whitespace, deduplicates, and filters empty values.
     *
     * @return string[] Normalized, deduplicated, trimmed tag names
     */
    public function parseTagString(?string $tagString): array
    {
        if (null === $tagString || '' === trim($tagString)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(trim(...), preg_split('#[,/]#', $tagString)))));
    }

    /**
     * Convert old category/theme strings to new Tag entities.
     *
     * - First category term becomes the Category (Tag)
     * - Remaining category terms + all theme terms become Themes (Tags)
     *
     * @return array{category: ?Tag, themes: Tag[]}
     */
    public function convert(?string $categoryString, ?string $themeString): array
    {
        $categoryTerms = $this->parseTagString($categoryString);
        $themeTerms = $this->parseTagString($themeString);

        $category = null;
        if (\count($categoryTerms) > 0) {
            // First term becomes the category
            $category = $this->tagRepository->findOrCreateByName($categoryTerms[0], true);

            // Remaining category terms become themes
            $overflowTerms = \array_slice($categoryTerms, 1);
            $themeTerms = array_merge($overflowTerms, $themeTerms);
        }

        // Deduplicate theme terms and exclude the category name
        $themeTermsUnique = [];
        $categoryName = null !== $category ? mb_strtolower($category->getName()) : null;

        foreach ($themeTerms as $term) {
            $termLower = mb_strtolower($term);
            // Skip if same as category or already added
            if ($termLower === $categoryName || isset($themeTermsUnique[$termLower])) {
                continue;
            }
            $themeTermsUnique[$termLower] = $term;
        }

        $themes = [];
        foreach ($themeTermsUnique as $term) {
            $themes[] = $this->tagRepository->findOrCreateByName($term, true);
        }

        return ['category' => $category, 'themes' => $themes];
    }
}
