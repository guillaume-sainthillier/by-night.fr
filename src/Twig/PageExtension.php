<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use stdClass;
use Twig\Attribute\AsTwigFunction;

final class PageExtension
{
    /**
     * Emit the HTML attributes consumed by the `data-page` listener of the
     * `@silarhi/app-js` package to wire a page initializer. Attach to any
     * element; the listener scans for `[data-page]` markers on every mount,
     * imports the page chunk declared in the `pages` of assets/js/app.ts, and
     * hands the element to the initializer as its `container`.
     *
     * Usage:
     *   <div id="agenda" {{ load_page('agenda') }}>
     *   <div class="mb-4" {{ load_page('agenda_map', {events: mapEvents}) }}>
     *
     * @param string|array<string>      $pageIds
     * @param array<string, mixed>|null $params
     */
    #[AsTwigFunction(name: 'load_page', isSafe: ['html_attr'])]
    public function loadPage(string|array $pageIds, ?array $params = null): string
    {
        $page = json_encode((array) $pageIds, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $pageParams = json_encode($params ?? new stdClass(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return \sprintf(
            'data-page="%s" data-page-params="%s"',
            htmlspecialchars($page, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($pageParams, \ENT_QUOTES, 'UTF-8'),
        );
    }
}
