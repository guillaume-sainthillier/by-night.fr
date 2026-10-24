<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\App\AppContext;
use App\Entity\City;
use App\Stats\LocationPortalProvider;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Attribute\AsTwigFunction;

/**
 * Where the page of a client error (404, 410…) sends a visitor who lost their way.
 */
final readonly class ErrorPageExtension
{
    public function __construct(
        private LocationPortalProvider $locationPortalProvider,
        private AppContext $appContext,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The metropolises of the home page (a query cached for 10 minutes), for a page without a location. None when
     * they cannot be read: the page is already an error, its links must not make it fail.
     *
     * @return City[]
     */
    #[AsTwigFunction(name: 'error_page_cities')]
    public function errorPageCities(): array
    {
        try {
            return $this->locationPortalProvider->getMetropolises($this->appContext->getCountry());
        } catch (Throwable $e) {
            $this->logger->warning('The cities of the error page could not be read: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return [];
        }
    }
}
