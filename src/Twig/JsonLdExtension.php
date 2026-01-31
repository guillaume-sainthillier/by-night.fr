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
use App\SEO\EventJsonLd;
use Twig\Attribute\AsTwigFunction;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

final readonly class JsonLdExtension
{
    public function __construct(
        private EventJsonLd $eventJsonLd,
    ) {
    }

    #[AsTwigFunction(name: 'event_json_ld', isSafe: ['html'])]
    public function eventJsonLd(Event $event): string
    {
        $schema = $this->eventJsonLd->generateEventSchema($event);
        $json = $this->eventJsonLd->toJson($schema);

        return \sprintf('<script type="application/ld+json">%s</script>', $json);
    }

    #[AsTwigFunction(name: 'breadcrumb_json_ld', isSafe: ['html'])]
    public function breadcrumbJsonLd(Breadcrumbs $breadcrumbs): string
    {
        $items = [];
        $position = 1;

        foreach ($breadcrumbs as $breadcrumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $breadcrumb['text'],
            ];

            if (!empty($breadcrumb['url'])) {
                $item['item'] = $breadcrumb['url'];
            }

            $items[] = $item;
            ++$position;
        }

        if ([] === $items) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        $json = json_encode($schema, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);

        return \sprintf('<script type="application/ld+json">%s</script>', $json);
    }
}
