<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Pagination;

use Override;
use Pagerfanta\Pagerfanta;

/**
 * Fetches the current page before counting, so the count comes with the page.
 *
 * Pagerfanta's Doctrine ORM adapter runs the ids, rows and COUNT queries on every slice
 * (OffsetPaginator), and answers a count asked before any slice with a one-row probe
 * slice. Asking hasNextPage() or nbResults first therefore ran all three queries twice.
 *
 * @template T
 *
 * @extends Pagerfanta<T>
 */
final class PageFirstPagerfanta extends Pagerfanta
{
    #[Override]
    public function getNbResults(): int
    {
        $this->getCurrentPageResults();

        return parent::getNbResults();
    }
}
