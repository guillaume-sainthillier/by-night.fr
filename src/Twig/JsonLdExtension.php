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
use App\SEO\BreadcrumbJsonLd;
use App\SEO\EventJsonLd;
use App\SEO\SiteJsonLd;
use Twig\Attribute\AsTwigFunction;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

final readonly class JsonLdExtension
{
    public function __construct(
        private EventJsonLd $eventJsonLd,
        private SiteJsonLd $siteJsonLd,
        private BreadcrumbJsonLd $breadcrumbJsonLd,
    ) {
    }

    #[AsTwigFunction(name: 'event_json_ld', isSafe: ['html'])]
    public function eventJsonLd(Event $event): string
    {
        $json = $this->eventJsonLd->generateEventJsonLd($event);

        return \sprintf('<script type="application/ld+json">%s</script>', $json);
    }

    #[AsTwigFunction(name: 'site_json_ld', isSafe: ['html'])]
    public function siteJsonLd(): string
    {
        $json = $this->siteJsonLd->generateSiteJsonLd();

        return \sprintf('<script type="application/ld+json">%s</script>', $json);
    }

    #[AsTwigFunction(name: 'breadcrumb_json_ld', isSafe: ['html'])]
    public function breadcrumbJsonLd(Breadcrumbs $breadcrumbs): string
    {
        $json = $this->breadcrumbJsonLd->generateBreadcrumbJsonLd($breadcrumbs);

        if ('' === $json) {
            return '';
        }

        return \sprintf('<script type="application/ld+json">%s</script>', $json);
    }
}
