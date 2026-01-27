<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto\WidgetData;

use Pagerfanta\PagerfantaInterface;

final readonly class TopUsersWidgetData
{
    /**
     * @param PagerfantaInterface<\App\Entity\User> $paginator
     */
    public function __construct(
        public PagerfantaInterface $paginator,
        public ?string $hasNextLink,
    ) {
    }

    /**
     * @return array<\App\Entity\User>
     */
    public function getUsers(): array
    {
        return iterator_to_array($this->paginator->getCurrentPageResults());
    }

    public function getCount(): int
    {
        return $this->paginator->getNbResults();
    }

    public function getCurrent(): int
    {
        return $this->paginator->getCurrentPage() * $this->paginator->getMaxPerPage();
    }
}
