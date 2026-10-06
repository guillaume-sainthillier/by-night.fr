<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\SEO\BreadcrumbJsonLd;
use App\SEO\EventJsonLd;
use App\SEO\PlaceJsonLd;
use App\SEO\ProfilePageJsonLd;
use App\SEO\SiteJsonLd;
use App\Ticketing\TicketOffer;
use Huluti\BreadcrumbsBundle\Model\Breadcrumbs;
use Twig\Attribute\AsTwigFunction;

final readonly class JsonLdExtension
{
    public function __construct(
        private EventJsonLd $eventJsonLd,
        private SiteJsonLd $siteJsonLd,
        private BreadcrumbJsonLd $breadcrumbJsonLd,
        private PlaceJsonLd $placeJsonLd,
        private ProfilePageJsonLd $profilePageJsonLd,
    ) {
    }

    #[AsTwigFunction(name: 'place_json_ld', isSafe: ['html'])]
    public function placeJsonLd(Place $place): string
    {
        return $this->script($this->placeJsonLd->generatePlaceJsonLd($place));
    }

    #[AsTwigFunction(name: 'profile_page_json_ld', isSafe: ['html'])]
    public function profilePageJsonLd(User $user): string
    {
        return $this->script($this->profilePageJsonLd->generateProfilePageJsonLd($user));
    }

    /**
     * @param list<TicketOffer>|null $ticketOffers the offers the page lists, not to read them twice
     */
    #[AsTwigFunction(name: 'event_json_ld', isSafe: ['html'])]
    public function eventJsonLd(Event $event, ?array $ticketOffers = null): string
    {
        return $this->script($this->eventJsonLd->generateEventJsonLd($event, $ticketOffers));
    }

    #[AsTwigFunction(name: 'site_json_ld', isSafe: ['html'])]
    public function siteJsonLd(): string
    {
        return $this->script($this->siteJsonLd->generateSiteJsonLd());
    }

    #[AsTwigFunction(name: 'breadcrumb_json_ld', isSafe: ['html'])]
    public function breadcrumbJsonLd(Breadcrumbs $breadcrumbs): string
    {
        $json = $this->breadcrumbJsonLd->generateBreadcrumbJsonLd($breadcrumbs);

        if ('' === $json) {
            return '';
        }

        return $this->script($json);
    }

    /**
     * Names, places and search terms end up in these blocks: a "</script>" among them would close
     * the element and let the rest run as HTML. JSON only carries "<", ">" and "&" inside strings,
     * where their unicode escapes decode to the same text.
     */
    private function script(string $json): string
    {
        $unsafe = ['<', '>', '&'];
        $escaped = array_map(static fn (string $char): string => \sprintf('\\u%04X', \ord($char)), $unsafe);

        return \sprintf('<script type="application/ld+json">%s</script>', str_replace($unsafe, $escaped, $json));
    }
}
