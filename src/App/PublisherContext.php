<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\Publisher;
use LogicException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The publisher whose organizer pages the member is on (/espace-perso/organisateurs/{publisher}/…), resolved from the
 * URL by PublisherContextSubscriber. Never from the session: two tabs on two publishers stay apart.
 */
final class PublisherContext implements ResetInterface
{
    private ?Publisher $publisher = null;

    public function set(Publisher $publisher): void
    {
        $this->publisher = $publisher;
    }

    public function find(): ?Publisher
    {
        return $this->publisher;
    }

    public function get(): Publisher
    {
        return $this->publisher ?? throw new LogicException('This page is not one of a publisher.');
    }

    public function reset(): void
    {
        $this->publisher = null;
    }
}
