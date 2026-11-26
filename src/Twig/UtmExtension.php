<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\Utm;
use Twig\Attribute\AsTwigFilter;

final readonly class UtmExtension
{
    /**
     * Tags a link with the UTM parameters of its template, the "utm" of its context (Utm): an e-mail worth measuring,
     * a widget. A template without one leaves its links as they are, so that the e-mail layouts tag theirs whatever
     * the e-mail.
     *
     * @param array<string, mixed> $context
     * @param string|null          $content which link it is: "event", "cta"
     */
    #[AsTwigFilter(name: 'utm', needsContext: true)]
    public function utm(array $context, string $url, ?string $content = null): string
    {
        $utm = $context['utm'] ?? null;
        if (!\is_array($utm) || [] === $utm) {
            return $url;
        }

        return Utm::tag($url, null === $content ? $utm : $utm + ['utm_content' => $content]);
    }
}
