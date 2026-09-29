<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\PriceLabel;
use Symfony\Component\String\TruncateMode;

use function Symfony\Component\String\u;

use Twig\Attribute\AsTwigFilter;

final class StringExtension
{
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
     * The start of an HTML text as plain text: tags stripped, entities decoded ("&nbsp;", "&amp;"), whitespace
     * collapsed, cut between two words to fit in $length characters, the ellipsis included. The no-break spaces
     * stay, so a cut never parts a word from its "?", "!" or ":". Autoescaping applies to the result as to any string.
     */
    #[AsTwigFilter(name: 'excerpt')]
    public function excerpt(?string $html, int $length = 160): string
    {
        // A space where a block starts or ends, so that paragraphs do not run into each other once stripped
        $html = (string) preg_replace('~<(/?(?:p|div|br|li|h[1-6])\b)~i', ' <$1', (string) $html);

        return u(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))
            // A run of spaces becomes one space as soon as it holds a breaking one ("&nbsp;</p> <p>"); a no-break space
            // on its own stays. \s also matches the no-break spaces here: /u makes it Unicode-aware
            ->replaceMatches('/\s*[\t\n\v\f\r ]\s*/u', ' ')
            ->trim()
            ->truncate($length, '…', TruncateMode::WordBefore)
            ->toString();
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
