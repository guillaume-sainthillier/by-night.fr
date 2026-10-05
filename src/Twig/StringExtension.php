<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\HtmlExcerpter;
use App\Utils\PriceLabel;
use Twig\Attribute\AsTwigFilter;

final readonly class StringExtension
{
    public function __construct(private HtmlExcerpter $htmlExcerpter)
    {
    }

    /**
     * Upper-cases the first letter only. Twig's |capitalize also lower-cases the rest,
     * which mangles proper nouns and acronyms ("Zénith toulouse métropole", "Dj").
     */
    #[AsTwigFilter(name: 'ucfirst')]
    public function ucfirst(string $text): string
    {
        return mb_ucfirst($text);
    }

    /**
     * The start of an HTML text as plain text (HtmlExcerpter). Autoescaping applies to the result as to any string.
     */
    #[AsTwigFilter(name: 'excerpt')]
    public function excerpt(?string $html, int $length = 160): string
    {
        return $this->htmlExcerpter->excerpt($html, $length);
    }

    /**
     * The price badge of an event card: {label: "Gratuit", free: true}, or null to show none.
     *
     * @return array{label: string, free: bool}|null
     */
    #[AsTwigFilter(name: 'price_label')]
    public function priceLabel(?string $prices): ?array
    {
        return PriceLabel::fromPrices($prices);
    }
}
