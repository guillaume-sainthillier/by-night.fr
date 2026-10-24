<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Utils\EmailUtm;
use Twig\Attribute\AsTwigFilter;

final readonly class UtmExtension
{
    /**
     * Tags a link of an e-mail with the UTM parameters of its campaign, the "utm" of its context (EmailUtm): a link of
     * an e-mail without one stays as it is, so that the layouts tag theirs whatever the e-mail.
     *
     * @param array<string, mixed> $context
     * @param string|null          $content which link of the e-mail it is: "event", "cta"
     */
    #[AsTwigFilter(name: 'utm', needsContext: true)]
    public function utm(array $context, string $url, ?string $content = null): string
    {
        $utm = $context['utm'] ?? null;
        if (!\is_array($utm) || [] === $utm) {
            return $url;
        }

        return EmailUtm::tag($url, null === $content ? $utm : $utm + ['utm_content' => $content]);
    }
}
