<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Updater;

use App\Social\FacebookAdmin;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class Updater
{
    protected HttpClientInterface $client;

    protected EntityManagerInterface $entityManager;

    protected FacebookAdmin $facebookAdmin;

    protected LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, FacebookAdmin $facebookAdmin)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->facebookAdmin = $facebookAdmin;

        $this->client = HttpClient::create();
    }

    abstract public function update(DateTimeInterface $from);
}
