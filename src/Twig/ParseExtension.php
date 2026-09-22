<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\HtmlFormatter;
use Twig\Attribute\AsTwigFilter;

final readonly class ParseExtension
{
    public function __construct(private HtmlFormatter $htmlFormatter)
    {
    }

    #[AsTwigFilter(name: 'ensure_protocol')]
    public function ensureProtocol(?string $link): ?string
    {
        return $this->htmlFormatter->ensureProtocol($link);
    }

    #[AsTwigFilter(name: 'parse_tags')]
    public function parseTags(?string $html): string
    {
        return $this->htmlFormatter->format($html);
    }
}
