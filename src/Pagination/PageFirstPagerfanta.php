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
use Pagerfanta\PagerfantaInterface;

/**
 * Fetches the current page before counting, so the count comes with the page.
 *
 * Pagerfanta's Doctrine ORM adapter runs the ids, rows and COUNT queries on every slice
 * (OffsetPaginator), and answers a count asked before any slice with a one-row probe
 * slice. Asking hasNextPage() or nbResults first therefore ran all three queries twice.
 *
 * A page past setMaxNbPages() is never fetched: the cap is what keeps an Elasticsearch
 * query within its result window ("/grenoble/2021" asked for hits 30,300 and up), and the
 * count alone leads such a page to the last one allowed.
 *
 * @template T
 *
 * @extends Pagerfanta<T>
 */
final class PageFirstPagerfanta extends Pagerfanta
{
    /** Pagerfanta keeps its own private, without a getter */
    private ?int $maxNbPages = null;

    #[Override]
    public function getNbResults(): int
    {
        if (null === $this->maxNbPages || $this->getCurrentPage() <= $this->maxNbPages) {
            $this->getCurrentPageResults();
        }

        return parent::getNbResults();
    }

    #[Override]
    public function setMaxNbPages(int $maxNbPages): PagerfantaInterface
    {
        parent::setMaxNbPages($maxNbPages);
        $this->maxNbPages = $maxNbPages;

        return $this;
    }

    #[Override]
    public function resetMaxNbPages(): PagerfantaInterface
    {
        parent::resetMaxNbPages();
        $this->maxNbPages = null;

        return $this;
    }
}
