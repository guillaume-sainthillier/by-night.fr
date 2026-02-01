<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;
use WhiteOctober\BreadcrumbsBundle\Model\SingleBreadcrumb;

final readonly class BreadcrumbJsonLd
{
    public function generateBreadcrumbJsonLd(Breadcrumbs $breadcrumbs): string
    {
        $items = [];
        $position = 1;

        /** @var SingleBreadcrumb $breadcrumb */
        foreach ($breadcrumbs as $breadcrumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $breadcrumb->text,
            ];

            if (!empty($breadcrumb->url)) {
                $item['item'] = $breadcrumb->url;
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

        return json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
