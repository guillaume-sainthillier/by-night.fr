<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\App\SocialManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SiteJsonLd
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Packages $packages,
        private SocialManager $socialManager,
    ) {
    }

    public function generateSiteJsonLd(): string
    {
        $schema = $this->generateSiteSchema();

        return json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSiteSchema(): array
    {
        $baseUrl = $this->urlGenerator->generate('app_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $searchUrl = $this->urlGenerator->generate('app_search_index', ['q' => '{search_term_string}'], UrlGeneratorInterface::ABSOLUTE_URL);
        // URL decode the search URL to keep {search_term_string} as-is for schema.org
        $searchUrl = urldecode($searchUrl);

        $organization = [
            '@type' => 'Organization',
            '@id' => $baseUrl . '#organization',
            'name' => 'By Night',
            'url' => $baseUrl,
            'logo' => $this->packages->getUrl('build/images/by-night.png', 'local'),
        ];

        $sameAs = $this->buildSameAsLinks();
        if ([] !== $sameAs) {
            $organization['sameAs'] = $sameAs;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $baseUrl . '#website',
                    'url' => $baseUrl,
                    'name' => 'By Night',
                    'publisher' => [
                        '@id' => $baseUrl . '#organization',
                    ],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => $searchUrl,
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                $organization,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function buildSameAsLinks(): array
    {
        $sameAs = [];

        $facebookId = $this->socialManager->getFacebookIdPage();
        if ('' !== $facebookId) {
            $sameAs[] = 'https://www.facebook.com/' . $facebookId;
        }

        $twitterId = $this->socialManager->getTwitterIdPage();
        if ('' !== $twitterId) {
            $sameAs[] = 'https://twitter.com/' . $twitterId;
        }

        return $sameAs;
    }
}
