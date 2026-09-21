<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Maps internal social service identifiers (route params, OAuth clients, DB
 * columns — e.g. `twitter`, `facebook_admin`) to what users should see.
 */
final class SocialExtension
{
    #[AsTwigFilter(name: 'social_label')]
    public function socialLabel(string $service): string
    {
        $service = $this->normalize($service);

        return match ($service) {
            'twitter' => 'X',
            default => ucfirst($service),
        };
    }

    #[AsTwigFilter(name: 'social_icon')]
    public function socialIcon(string $service): string
    {
        $service = $this->normalize($service);

        return 'fa7-brands:' . match ($service) {
            'twitter' => 'x-twitter',
            default => $service,
        };
    }

    /**
     * Admin connections (`twitter_admin`, ...) share the brand of their user counterpart.
     */
    private function normalize(string $service): string
    {
        return str_ends_with($service, '_admin') ? substr($service, 0, -\strlen('_admin')) : $service;
    }
}
