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
     * Emit the HTML attributes consumed by `listeners/pages.js` to wire a
     * page initializer. Attach to any element; the JS listener scans for
     * `[data-page]` markers on every mount.
     *
     * Usage:
     *   <div {{ load_page('search') }}></div>
     *   <div class="row" {{ load_page('user', {datas: places}) }}>
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
